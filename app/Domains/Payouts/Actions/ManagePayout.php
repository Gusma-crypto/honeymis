<?php

namespace App\Domains\Payouts\Actions;

use App\Domains\Commissions\Models\CommissionEntry;
use App\Domains\Partners\Models\Partner;
use App\Domains\Payouts\Models\PayoutRequest;
use App\Domains\Shared\Services\AuditWriter;
use App\Domains\Shared\Services\CommerceNotifier;
use App\Domains\Wallet\Services\PartnerLedger;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManagePayout
{
    public function __construct(private PartnerLedger $ledger, private AuditWriter $audit) {}

    /** @param array{bank_name: string, account_name: string, account_number: string} $bank */
    public function request(User $user, Partner $partner, int $amount, array $bank, string $key): PayoutRequest
    {
        Gate::forUser($user)->authorize('requestPayout', $partner);
        $fingerprint = hash('sha256', json_encode([$amount, $bank], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $partner, $amount, $bank, $key, $fingerprint): PayoutRequest {
            $locked = Partner::whereKey($partner->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('requestPayout', $locked);
            $existing = PayoutRequest::where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if ($existing->partner_id !== $partner->id || $existing->request_fingerprint !== $fingerprint) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key payout telah dipakai untuk permintaan lain.']);
                }

                return $existing;
            }
            $this->ledger->settleDebt($partner->id, 'payout-request:'.$key.':offset');
            if ($amount < max(1, config('partners.minimum_payout_idr')) || $amount > $this->ledger->withdrawable($partner->id)) {
                throw ValidationException::withMessages(['amount' => 'Nominal di bawah minimum atau melebihi saldo yang dapat ditarik.']);
            }
            $payout = PayoutRequest::create(['partner_id' => $partner->id, 'amount' => $amount, 'idempotency_key' => $key, 'request_fingerprint' => $fingerprint,
                'bank_account_encrypted' => $bank, 'bank_last4' => substr($bank['account_number'], -4), 'status' => 'requested', 'requested_at' => now()]);
            $remaining = $amount;
            foreach (CommissionEntry::where('beneficiary_partner_id', $partner->id)->whereNotNull('available_at')->whereHas('item.order', fn ($query) => $query->where('fraud_hold', false)->whereIn('status', ['delivered', 'completed']))->orderBy('id')->lockForUpdate()->get() as $entry) {
                $allocation = min($remaining, $this->ledger->availableFor($entry));
                if ($allocation < 1) {
                    continue;
                }
                DB::table('payout_allocations')->insert(['payout_id' => $payout->id, 'commission_entry_id' => $entry->id, 'partner_id' => $partner->id, 'amount' => $allocation]);
                $this->ledger->refreshStatus($entry);
                $remaining -= $allocation;
                if ($remaining === 0) {
                    break;
                }
            }
            if ($remaining !== 0) {
                throw ValidationException::withMessages(['amount' => 'Sebagian dana tertahan atau berubah; muat ulang dompet.']);
            }
            $this->ledger->move($partner->id, 'available', 'reserved', $amount, 'reserve', 'payout:'.$payout->id.':reserve', payoutId: $payout->id);
            app(CommerceNotifier::class)->queue($user->id, 'payout:'.$payout->id.':requested', 'Payout diajukan', 'Payout #'.$payout->id.' menunggu peninjauan.', route('payouts.index', absolute: false));
            $this->audit->record($user->id, 'payout_requested', 'payout_requests', $payout->id, 'payout:'.$payout->id.':requested', ['amount' => $amount]);

            return $payout;
        }, attempts: 3);
    }

    public function decide(User $actor, PayoutRequest $payout, string $operation, string $key, ?string $reason = null, ?string $reference = null, ?UploadedFile $proof = null, bool $confirmed = false): void
    {
        Gate::forUser($actor)->authorize('review', $payout);
        $proofHash = $proof === null ? null : hash_file('sha256', $proof->getRealPath());
        $path = $proof === null ? null : 'payout-proofs/'.$payout->id.'/'.$proofHash.'.'.$proof->extension();
        $wasPresent = $path !== null && Storage::disk('local')->exists($path);
        try {
            DB::transaction(function () use ($actor, $payout, $operation, $key, $reason, $reference, $proof, $confirmed, $proofHash, $path): void {
                Partner::whereKey($payout->partner_id)->lockForUpdate()->firstOrFail();
                $locked = PayoutRequest::whereKey($payout->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('review', $locked);
                $state = ['operation' => $operation, 'reference_hash' => $reference === null ? null : hash('sha256', $reference), 'proof_hash' => $proofHash, 'reason_hash' => $reason === null ? null : hash('sha256', $reason)];
                $existing = DB::table('audit_logs')->where('idempotency_key', 'payout-decision:'.$key)->first();
                if ($existing !== null) {
                    if ((int) $existing->subject_id !== $locked->id || (int) $existing->actor_id !== $actor->id || json_decode($existing->after_json, true, flags: JSON_THROW_ON_ERROR) != $state) {
                        throw ValidationException::withMessages(['idempotency_key' => 'Key telah dipakai untuk keputusan lain.']);
                    }

                    return;
                }
                if (in_array($operation, ['approve', 'paid'], true)) {
                    if ($locked->partner->status !== 'active' || ! $locked->partner->user->isActive() || DB::table('payout_allocations')->join('commission_entries', 'commission_entries.id', '=', 'payout_allocations.commission_entry_id')->join('order_items', 'order_items.id', '=', 'commission_entries.order_item_id')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('payout_id', $locked->id)->where('orders.fraud_hold', true)->exists()) {
                        throw ValidationException::withMessages(['operation' => 'Mitra atau dana sedang ditahan; payout harus ditinjau ulang.']);
                    }
                }
                if ($operation === 'approve' && $locked->status === 'requested') {
                    $locked->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
                } elseif ($operation === 'paid' && $locked->status === 'approved') {
                    if (! $confirmed || $proof === null || empty($reference) || $path === null) {
                        throw ValidationException::withMessages(['proof' => 'Konfirmasi transfer manual, referensi unik dan bukti wajib diisi.']);
                    }
                    if (PayoutRequest::where('transfer_reference', $reference)->where('id', '!=', $locked->id)->exists()) {
                        throw ValidationException::withMessages(['transfer_reference' => 'Referensi transfer sudah digunakan.']);
                    }
                    if (Storage::disk('local')->putFileAs(dirname($path), $proof, basename($path)) === false) {
                        throw ValidationException::withMessages(['proof' => 'Bukti gagal disimpan.']);
                    }
                    $locked->update(['status' => 'paid', 'paid_at' => now(), 'proof_storage_key' => $path, 'transfer_reference' => $reference]);
                    $this->ledger->move($locked->partner_id, 'reserved', 'paid', $locked->amount, 'payout', 'payout:'.$locked->id.':paid', payoutId: $locked->id);
                } elseif (($operation === 'reject' && in_array($locked->status, ['requested', 'approved'], true)) || ($operation === 'fail' && $locked->status === 'approved')) {
                    if (empty($reason)) {
                        throw ValidationException::withMessages(['reason' => 'Alasan keputusan wajib diisi.']);
                    }
                    $locked->update(['status' => $operation === 'reject' ? 'rejected' : 'failed', 'rejection_reason' => $reason]);
                    $this->ledger->move($locked->partner_id, 'reserved', 'available', $locked->amount, 'reserve', 'payout:'.$locked->id.':release', payoutId: $locked->id);
                    $this->ledger->settleDebt($locked->partner_id, 'payout:'.$locked->id.':debt-offset');
                } else {
                    throw ValidationException::withMessages(['operation' => 'Transisi payout tidak valid.']);
                }
                $this->refreshAllocations($locked->id);
                app(CommerceNotifier::class)->queue($locked->partner->user_id, 'payout-decision:'.$key, 'Status payout', 'Payout #'.$locked->id.': '.$locked->status, route('payouts.index', absolute: false));
                $this->audit->record($actor->id, 'payout_decided', 'payout_requests', $locked->id, 'payout-decision:'.$key, $state);
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($path !== null && ! $wasPresent && ! PayoutRequest::where('proof_storage_key', $path)->exists()) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function releaseForRefund(PayoutRequest $payout, int $refundId): void
    {
        if (! in_array($payout->status, ['requested', 'approved'], true)) {
            return;
        }
        $payout->update(['status' => 'rejected', 'rejection_reason' => 'Dibatalkan untuk penyesuaian refund.']);
        $this->ledger->move($payout->partner_id, 'reserved', 'available', $payout->amount, 'reserve', 'payout:'.$payout->id.':refund-release', payoutId: $payout->id, refundId: $refundId);
        $this->refreshAllocations($payout->id);
        $this->audit->record(null, 'payout_cancelled_for_refund', 'payout_requests', $payout->id, 'payout:'.$payout->id.':refund-cancel', ['refund_id' => $refundId]);
        app(CommerceNotifier::class)->queue($payout->partner->user_id, 'payout:'.$payout->id.':refund-cancel', 'Payout ditinjau ulang', 'Payout #'.$payout->id.' dibatalkan untuk penyesuaian refund.', route('payouts.index', absolute: false));
    }

    private function refreshAllocations(int $payoutId): void
    {
        foreach (CommissionEntry::whereIn('id', DB::table('payout_allocations')->select('commission_entry_id')->where('payout_id', $payoutId))->orderBy('id')->lockForUpdate()->get() as $entry) {
            $this->ledger->refreshStatus($entry);
        }
    }
}
