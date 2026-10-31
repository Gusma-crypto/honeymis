<?php

namespace Database\Seeders;

use App\Domains\Shared\Enums\SystemPermission;
use App\Domains\Shared\Enums\SystemRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            foreach (SystemPermission::cases() as $permission) {
                Permission::findOrCreate($permission->value, 'web');
            }
            foreach (SystemRole::cases() as $systemRole) {
                $role = Role::findOrCreate($systemRole->value, 'web');
                $role->syncPermissions(array_map(fn (SystemPermission $permission): string => $permission->value, $systemRole->permissions()));
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
