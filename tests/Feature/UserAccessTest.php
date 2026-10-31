<?php

namespace Tests\Feature;

use App\Actions\Users\UpdateUserAccess;
use App\Domains\Shared\Enums\SystemPermission;
use App\Domains\Shared\Enums\SystemRole;
use App\Domains\Shared\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string, array{SystemRole}> */
    public static function unprivilegedRoles(): iterable
    {
        yield 'store admin' => [SystemRole::StoreAdmin];
        yield 'finance' => [SystemRole::Finance];
        yield 'partner' => [SystemRole::Partner];
        yield 'customer' => [SystemRole::Customer];
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_returns_403_for_role_without_user_access_permission(SystemRole $role): void
    {
        $this->seed(RolePermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole($role->value);
        $subject = User::factory()->create();

        $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.users.index'))->assertForbidden();
        $this->patch(route('admin.users.access.update', $subject), $this->payload())->assertForbidden();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_admin_requires_recent_password_confirmation(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertRedirect(route('password.confirm'));
    }

    public function test_unverified_admin_cannot_access_management(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->unverified()->create();
        $admin->assignRole(SystemRole::SuperAdmin->value);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_admin_list_exposes_only_required_user_fields(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('admin.users.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/users/index')
            ->has('users.data', 1)->where('users.data.0.id', $admin->id)->where('users.data.0.canUpdate', false)
            ->missing('users.data.0.password')->missing('users.data.0.two_factor_secret')->has('availableRoles', 5));
    }

    public function test_role_and_status_change_are_audited_once_on_retry(): void
    {
        $admin = $this->administrator();
        $subject = User::factory()->create();
        $subject->assignRole(SystemRole::Customer->value);
        $payload = $this->payload();
        $payload['status'] = 'suspended';

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('admin.users.access.update', $subject), $payload)->assertRedirect(route('admin.users.index'));
        $this->patch(route('admin.users.access.update', $subject), $payload)->assertRedirect(route('admin.users.index'));

        $this->assertSame(['finance'], $subject->fresh()->getRoleNames()->all());
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame(UserStatus::Suspended, $subject->fresh()->status);
        $audit = DB::table('audit_logs')->first();
        $beforeState = json_decode($audit->before_json, true, flags: JSON_THROW_ON_ERROR);
        $afterState = json_decode($audit->after_json, true, flags: JSON_THROW_ON_ERROR);
        ksort($beforeState);
        ksort($afterState);
        $this->assertSame(['roles' => ['customer'], 'status' => 'active'], $beforeState);
        $this->assertSame(['roles' => ['finance'], 'status' => 'suspended'], $afterState);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_access_updated', 'subject_id' => $subject->id, 'actor_id' => $admin->id]);
    }

    public function test_replay_key_with_changed_payload_is_rejected_without_changing_roles(): void
    {
        $admin = $this->administrator();
        $subject = User::factory()->create();
        $payload = $this->payload();
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('admin.users.access.update', $subject), $payload)->assertRedirect();
        $payload['roles'] = ['super_admin'];

        $this->patch(route('admin.users.access.update', $subject), $payload)->assertSessionHasErrors('idempotency_key');

        $this->assertSame(['finance'], $subject->fresh()->getRoleNames()->all());
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_self_role_changes_are_forbidden_even_for_super_admin(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('admin.users.access.update', $admin), $this->payload())->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole(SystemRole::SuperAdmin->value));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'unknown role' => [['roles' => ['invented']], 'roles.0'];
        yield 'duplicate role' => [['roles' => ['finance', 'finance']], 'roles.0'];
        yield 'empty roles' => [['roles' => []], 'roles'];
        yield 'invalid status' => [['status' => 'deleted'], 'status'];
        yield 'invalid request key' => [['idempotency_key' => 'not-a-uuid'], 'idempotency_key'];
        yield 'direct permissions prohibited' => [['permissions' => ['users.manage-access']], 'permissions'];
        yield 'email change prohibited' => [['email' => 'attacker@example.test'], 'email'];
        yield 'password change prohibited' => [['password' => 'attacker-password'], 'password'];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_access_payload_is_rejected(array $changes, string $error): void
    {
        $admin = $this->administrator();
        $subject = User::factory()->create();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('admin.users.access.update', $subject), array_replace($this->payload(), $changes))->assertSessionHasErrors($error);

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame([], $subject->fresh()->getRoleNames()->all());
    }

    public function test_suspending_an_account_revokes_remember_token_and_denies_next_request(): void
    {
        $admin = $this->administrator();
        $subject = User::factory()->create();
        $payload = $this->payload();
        $payload['status'] = 'suspended';

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('admin.users.access.update', $subject), $payload)->assertRedirect();

        $this->assertNull($subject->fresh()->remember_token);
        $this->actingAs($subject->fresh())->get(route('dashboard'))->assertForbidden();
    }

    public function test_last_active_super_admin_cannot_be_demoted_by_delegated_manager(): void
    {
        $lastAdmin = $this->administrator();
        $manager = User::factory()->create();
        $manager->givePermissionTo(SystemPermission::ManageUserAccess->value);

        $this->actingAs($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('admin.users.access.update', $lastAdmin), $this->payload())->assertSessionHasErrors('roles');

        $this->assertTrue($lastAdmin->fresh()->hasRole(SystemRole::SuperAdmin->value));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_missing_seeded_role_rolls_back_role_changes(): void
    {
        $admin = $this->administrator();
        $subject = User::factory()->create();
        $subject->assignRole(SystemRole::Customer->value);
        Role::findByName(SystemRole::Finance->value, 'web')->delete();

        try {
            (new UpdateUserAccess)->handle($admin, $subject, [SystemRole::Finance], UserStatus::Active, (string) Str::uuid());
            $this->fail('A missing seeded role must not remove existing roles.');
        } catch (ValidationException) {
            $this->assertSame(['customer'], $subject->fresh()->getRoleNames()->all());
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    private function administrator(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(SystemRole::SuperAdmin->value);

        return $user;
    }

    /** @return array{roles: list<string>, status: string, idempotency_key: string} */
    private function payload(): array
    {
        return ['roles' => ['finance'], 'status' => 'active', 'idempotency_key' => (string) Str::uuid()];
    }
}
