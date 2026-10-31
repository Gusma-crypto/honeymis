<?php

namespace App\Domains\Wallet\Services;

use App\Domains\Commissions\Models\CommissionEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Financial writers must hold the partner row lock throughout their transaction. */
class PartnerLedger
{
    /** @return array{pending: int, available: int, reserved: int, paid: int, receivable: int} */
    public function balances(int $partnerId): array
    {
        $totals = DB::table('wallet_ledger_entries')->where('partner_id', $partnerId)->selectRaw('bucket, SUM(amount_signed) as total')->groupBy('bucket')->pluck('total', 'bucket');

        return ['pending' => $this->integer($totals->get('pending', 0)), 'available' => $this->integer($totals->get('available', 0)),
            'reserved' => $this->integer($totals->get('reserved', 0)), 'paid' => $this->integer($totals->get('paid', 0)), 'receivable' => $this->integer($totals->get('receivable', 0))];
    }

    public function post(int $partnerId, string $bucket, int $amount, string $kind, string $key, ?int $commissionId = null, ?int $payoutId = null, ?int $refundId = null, ?string $transactionKey = null): void
    {
        if ($amount === 0) {
            return;
        }
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Ledger posting requires a database transaction.');
        }
        $existing = DB::table('wallet_ledger_entries')->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            if ((int) $existing->partner_id !== $partnerId || $existing->bucket !== $bucket || $this->integer($existing->amount_signed) !== $amount || $existing->commission_entry_id != $commissionId || $existing->payout_id != $payoutId || $existing->refund_id != $refundId) {
                throw new RuntimeException('Ledger idempotency key conflicts with existing content.');
            }

            return;
        }
        DB::table('wallet_ledger_entries')->insert(['partner_id' => $partnerId, 'commission_entry_id' => $commissionId, 'payout_id' => $payoutId, 'refund_id' => $refundId,
            'transaction_key' => $transactionKey ?? $key, 'idempotency_key' => $key, 'bucket' => $bucket, 'entry_type' => $kind, 'amount_signed' => $amount, 'currency' => 'IDR', 'created_at' => now()]);
    }

    public function move(int $partnerId, string $from, string $to, int $amount, string $kind, string $key, ?int $commissionId = null, ?int $payoutId = null, ?int $refundId = null): void
    {
        if ($amount < 0) {
            throw new RuntimeException('A ledger transfer cannot be negative.');
        }
        $this->post($partnerId, $from, -$amount, $kind, $key.':debit', $commissionId, $payoutId, $refundId, $key);
        $this->post($partnerId, $to, $amount, $kind, $key.':credit', $commissionId, $payoutId, $refundId, $key);
    }

    /** @param list<string> $statuses */
    public function allocated(int $entryId, array $statuses): int
    {
        return $this->integer(DB::table('payout_allocations')->join('payout_requests', 'payout_requests.id', '=', 'payout_allocations.payout_id')
            ->where('commission_entry_id', $entryId)->whereIn('payout_requests.status', $statuses)->sum('payout_allocations.amount'));
    }

    public function availableFor(CommissionEntry $entry): int
    {
        if ($entry->available_at === null) {
            return 0;
        }

        return max(0, $entry->amount - $entry->reversed_amount - $entry->offset_amount - $this->allocated($entry->id, ['requested', 'approved', 'paid']));
    }

    public function withdrawable(int $partnerId): int
    {
        $allocations = DB::table('payout_allocations')
            ->join('payout_requests', 'payout_requests.id', '=', 'payout_allocations.payout_id')
            ->where('payout_allocations.partner_id', $partnerId)
            ->whereIn('payout_requests.status', ['requested', 'approved', 'paid'])
            ->selectRaw('commission_entry_id, SUM(payout_allocations.amount) AS allocated')
            ->groupBy('commission_entry_id');
        $remaining = 'commission_entries.amount - commission_entries.reversed_amount - commission_entries.offset_amount - COALESCE(allocations.allocated, 0)';
        $total = CommissionEntry::where('beneficiary_partner_id', $partnerId)
            ->whereNotNull('available_at')
            ->whereHas('item.order', fn ($query) => $query->where('fraud_hold', false)->whereIn('status', ['delivered', 'completed']))
            ->leftJoinSub($allocations, 'allocations', 'allocations.commission_entry_id', '=', 'commission_entries.id')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.$remaining.' > 0 THEN '.$remaining.' ELSE 0 END), 0) AS total')
            ->value('total');
        $total = $this->integer($total);

        $balances = $this->balances($partnerId);

        return $balances['receivable'] > 0 ? 0 : min($balances['available'], $total);
    }

    public function refreshStatus(CommissionEntry $entry): void
    {
        if ($entry->reversed_amount >= $entry->amount) {
            $entry->update(['status' => $entry->available_at === null ? 'cancelled' : 'reversed']);
        } elseif ($entry->available_at === null) {
            $entry->update(['status' => 'pending']);
        } elseif ($this->availableFor($entry) > 0) {
            $entry->update(['status' => 'available']);
        } elseif ($this->allocated($entry->id, ['requested', 'approved']) > 0) {
            $entry->update(['status' => 'reserved']);
        } else {
            $entry->update(['status' => $this->allocated($entry->id, ['paid']) > 0 ? 'paid_out' : 'available']);
        }
    }

    public function settleDebt(int $partnerId, string $key, ?int $refundId = null): void
    {
        $balances = $this->balances($partnerId);
        $remaining = min($balances['receivable'], $balances['available']);
        if ($remaining <= 0) {
            return;
        }
        foreach (CommissionEntry::where('beneficiary_partner_id', $partnerId)->whereNotNull('available_at')->orderBy('id')->lockForUpdate()->get() as $entry) {
            $offset = min($remaining, $this->availableFor($entry));
            if ($offset < 1) {
                continue;
            }
            $offsetKey = $key.':'.$entry->id.':'.$entry->offset_amount;
            $entry->update(['offset_amount' => $entry->offset_amount + $offset]);
            $this->post($partnerId, 'available', -$offset, 'offset', $offsetKey.':available', $entry->id, refundId: $refundId);
            $this->post($partnerId, 'receivable', -$offset, 'offset', $offsetKey.':debt', $entry->id, refundId: $refundId);
            $remaining -= $offset;
            $this->refreshStatus($entry);
            if ($remaining === 0) {
                break;
            }
        }
    }

    private function integer(mixed $value): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if (! is_int($parsed)) {
            throw new RuntimeException('Ledger aggregate exceeds integer capacity.');
        }

        return $parsed;
    }
}
