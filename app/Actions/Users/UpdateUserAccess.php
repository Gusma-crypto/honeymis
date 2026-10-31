<?php

namespace App\Actions\Users;

use App\Domains\Shared\Enums\SystemRole;
use App\Domains\Shared\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UpdateUserAccess
{
    /** @param list<SystemRole> $roles */
    public function handle(User $actor, User $subject, array $roles, UserStatus $status, string $idempotencyKey): void
    {
        $roleNames = array_values(array_unique(array_map(fn (SystemRole $role): string => $role->value, $roles)));
        sort($roleNames);
        if ($roleNames === [] || trim($idempotencyKey) === '' || strlen($idempotencyKey) > 200) {
            throw ValidationException::withMessages(['roles' => 'Role dan idempotency key wajib diisi.']);
        }

        DB::transaction(function () use ($actor, $subject, $roleNames, $status, $idempotencyKey): void {
            Role::where('name', SystemRole::SuperAdmin->value)->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            $lockedActor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $lockedSubject = User::whereKey($subject->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($lockedActor)->authorize('updateAccess', $lockedSubject);

            $after = ['roles' => $roleNames, 'status' => $status->value];
            $auditKey = 'user-access:'.$idempotencyKey;
            $existingAudit = DB::table('audit_logs')->where('idempotency_key', $auditKey)->first();
            if ($existingAudit !== null) {
                if ($existingAudit->subject_type !== 'users' || (int) $existingAudit->subject_id !== $subject->id || (int) $existingAudit->actor_id !== $actor->id || json_decode($existingAudit->after_json, true, flags: JSON_THROW_ON_ERROR) != $after) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Key telah digunakan untuk perubahan lain.']);
                }

                return;
            }

            if ($lockedSubject->hasRole(SystemRole::SuperAdmin->value) && $lockedSubject->isActive()
                && ($status !== UserStatus::Active || ! in_array(SystemRole::SuperAdmin->value, $roleNames, true))
                && User::role(SystemRole::SuperAdmin->value)->where('status', UserStatus::Active->value)->count() <= 1) {
                throw ValidationException::withMessages(['roles' => 'Super Admin aktif terakhir tidak boleh dinonaktifkan atau diturunkan.']);
            }

            $beforeRoles = $lockedSubject->getRoleNames()->sort()->values()->all();
            $before = ['roles' => $beforeRoles, 'status' => $lockedSubject->status->value];
            $lockedSubject->syncRoles(Role::where('guard_name', 'web')->whereIn('name', $roleNames)->get());
            if ($lockedSubject->getRoleNames()->sort()->values()->all() !== $roleNames) {
                throw ValidationException::withMessages(['roles' => 'Jalankan RolePermissionSeeder sebelum mengatur akses.']);
            }
            $lockedSubject->forceFill(['status' => $status])->save();
            if ($status === UserStatus::Suspended) {
                $lockedSubject->forceFill(['remember_token' => null])->save();
            }
            DB::table('audit_logs')->insert([
                'actor_id' => $lockedActor->id, 'action' => 'user_access_updated', 'subject_type' => 'users',
                'subject_id' => $lockedSubject->id, 'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode($after, JSON_THROW_ON_ERROR), 'idempotency_key' => $auditKey, 'created_at' => now(),
            ]);
        }, attempts: 3);
    }
}
