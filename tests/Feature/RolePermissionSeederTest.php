<?php

namespace Tests\Feature;

use App\Domains\Shared\Enums\SystemPermission;
use App\Domains\Shared\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_roles_is_idempotent_and_does_not_create_demo_accounts(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('permissions', 17);
        $this->assertDatabaseCount('users', 0);
    }

    /** @return iterable<string, array{SystemRole, string, bool}> */
    public static function accessMatrix(): iterable
    {
        yield 'super admin can manage access' => [SystemRole::SuperAdmin, 'users.manage-access', true];
        yield 'finance verifies payments' => [SystemRole::Finance, 'payments.verify', true];
        yield 'finance cannot manage users' => [SystemRole::Finance, 'users.manage-access', false];
        yield 'store admin manages stock' => [SystemRole::StoreAdmin, 'inventory.manage', true];
        yield 'store admin cannot verify payments' => [SystemRole::StoreAdmin, 'payments.verify', false];
        yield 'partner requests own payout' => [SystemRole::Partner, 'payouts.request-own', true];
        yield 'partner cannot review payouts' => [SystemRole::Partner, 'payouts.review', false];
        yield 'partner cannot view every wallet' => [SystemRole::Partner, 'wallets.view', false];
        yield 'customer views own orders' => [SystemRole::Customer, 'orders.view-own', true];
        yield 'customer cannot manage catalog' => [SystemRole::Customer, 'catalog.manage', false];
    }

    #[DataProvider('accessMatrix')]
    public function test_role_permissions_enforce_the_access_matrix(SystemRole $role, string $permission, bool $allowed): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->assertSame($allowed, $user->can($permission));
    }

    public function test_suspended_super_admin_cannot_bypass_permission_checks(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->suspended()->create();
        $user->assignRole(SystemRole::SuperAdmin->value);

        foreach (SystemPermission::cases() as $permission) {
            $this->assertFalse($user->can($permission->value));
        }
    }
}
