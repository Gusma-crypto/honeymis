<?php

namespace App\Console\Commands;

use App\Domains\Shared\Enums\SystemRole;
use App\Domains\Shared\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class BootstrapAdministrator extends Command
{
    protected $signature = 'honey:bootstrap-admin {email : Email of an existing verified user} {--confirm : Explicitly approve the initial administrator grant}';

    protected $description = 'Grant the first Super Admin to an existing verified account in a non-production environment';

    public function handle(): int
    {
        if (app()->isProduction() || ! $this->option('confirm')) {
            $this->error('Only non-production bootstrap with --confirm is allowed.');

            return self::FAILURE;
        }

        return DB::transaction(function (): int {
            $role = Role::where('name', SystemRole::SuperAdmin->value)->where('guard_name', 'web')->lockForUpdate()->first();
            if ($role === null) {
                $this->error('Run RolePermissionSeeder first.');

                return self::FAILURE;
            }
            if (User::role(SystemRole::SuperAdmin->value)->exists()) {
                $this->error('An administrator already exists. Use the authorized access-management workflow.');

                return self::FAILURE;
            }
            $user = User::where('email', $this->argument('email'))->lockForUpdate()->first();
            if ($user === null || $user->status !== UserStatus::Active || ! $user->hasVerifiedEmail()) {
                $this->error('An active, verified existing account is required.');

                return self::FAILURE;
            }
            $before = ['roles' => $user->getRoleNames()->sort()->values()->all()];
            $user->assignRole($role);
            DB::table('audit_logs')->insert([
                'actor_id' => null, 'action' => 'initial_administrator_granted', 'subject_type' => 'users', 'subject_id' => $user->id,
                'before_json' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_json' => json_encode(['roles' => $user->getRoleNames()->sort()->values()->all()], JSON_THROW_ON_ERROR),
                'idempotency_key' => 'bootstrap-admin:'.Str::uuid(), 'created_at' => now(),
            ]);
            $this->info('Initial administrator granted and audited. Enable two-factor authentication before operational use.');

            return self::SUCCESS;
        }, attempts: 3);
    }
}
