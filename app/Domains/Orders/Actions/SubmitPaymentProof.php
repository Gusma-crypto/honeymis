<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Payment;
use App\Domains\Shared\Services\AuditWriter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmitPaymentProof
{
    public function __construct(private AuditWriter $audit) {}

    public function handle(User $user, Order $order, UploadedFile $file, string $key): void
    {
        $hash = hash_file('sha256', $file->getRealPath());
        $path = 'payment-proofs/'.$order->id.'/'.$hash.'.'.$file->extension();
        $wasPresent = Storage::disk('local')->exists($path);
        try {
            DB::transaction(function () use ($user, $order, $file, $key, $hash, $path): void {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('submitProof', $locked);
                $existing = DB::table('audit_logs')->where('idempotency_key', 'proof:'.$key)->first();
                if ($existing !== null) {
                    $metadata = json_decode($existing->after_json, true, flags: JSON_THROW_ON_ERROR);
                    if ((int) $existing->subject_id !== $order->id || (int) $existing->actor_id !== $user->id || $metadata['hash'] !== $hash) {
                        throw ValidationException::withMessages(['idempotency_key' => 'Key bukti pembayaran telah dipakai.']);
                    }

                    return;
                }
                if ($locked->status !== 'pending_payment' || ($locked->expires_at !== null && $locked->expires_at->isPast())) {
                    throw ValidationException::withMessages(['proof' => 'Order sudah berubah atau melewati batas pembayaran.']);
                }
                $payment = $locked->payments()->reorder('id', 'desc')->firstOrFail();
                if ($payment->status === 'rejected') {
                    $payment = Payment::create(['order_id' => $locked->id, 'provider' => 'manual', 'amount' => $locked->grand_total, 'status' => 'pending', 'idempotency_key' => 'manual-retry:'.$key]);
                }
                if ($payment->status !== 'pending') {
                    throw ValidationException::withMessages(['proof' => 'Bukti sedang ditinjau. Tunggu hasil verifikasi.']);
                }
                if (Storage::disk('local')->putFileAs(dirname($path), $file, basename($path)) === false) {
                    throw ValidationException::withMessages(['proof' => 'Bukti gagal disimpan. Coba lagi.']);
                }
                $payment->update(['status' => 'submitted', 'proof_storage_key' => $path, 'proof_hash' => $hash]);
                $this->audit->record($user->id, 'payment_proof_submitted', 'orders', $order->id, 'proof:'.$key, ['hash' => $hash]);
            }, attempts: 3);
        } catch (Throwable $exception) {
            if (! $wasPresent && ! Payment::where('proof_storage_key', $path)->exists()) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
