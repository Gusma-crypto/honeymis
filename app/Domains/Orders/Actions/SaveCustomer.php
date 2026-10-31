<?php

namespace App\Domains\Orders\Actions;

use App\Domains\Orders\Models\Customer;
use App\Domains\Shared\Services\AuditWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveCustomer
{
    public function __construct(private AuditWriter $audit) {}

    /** @param array{name:string,email:string,phone:?string} $values */
    public function handle(User $actor, ?Customer $customer, array $values, string $key): Customer
    {
        return DB::transaction(function () use ($actor, $customer, $values, $key): Customer {
            $lockedActor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($lockedActor)->authorize($customer === null ? 'create' : 'update', $customer ?? Customer::class);
            $auditKey = 'customer-save:'.$key;
            $fingerprint = hash('sha256', json_encode([$customer?->id, $values], JSON_THROW_ON_ERROR));
            $existing = DB::table('audit_logs')->where('idempotency_key', $auditKey)->first();
            if ($existing !== null) {
                $metadata = json_decode($existing->after_json, true, flags: JSON_THROW_ON_ERROR);
                if ((int) $existing->actor_id !== $actor->id || $existing->subject_type !== 'customers' || $metadata['fingerprint'] !== $fingerprint) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key telah digunakan untuk perubahan pelanggan lain.']);
                }

                return Customer::where('id', $existing->subject_id)->firstOrFail();
            }
            if ($customer === null) {
                $saved = Customer::create($values);
            } else {
                $saved = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                $saved->update($values);
            }
            $this->audit->record($actor->id, 'customer_saved', 'customers', $saved->id, $auditKey, ['fingerprint' => $fingerprint, 'fields' => array_keys($values)]);

            return $saved;
        }, attempts: 3);
    }
}
