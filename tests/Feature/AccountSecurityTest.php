<?php

namespace Tests\Feature;

use App\Domains\Shared\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_ignores_privilege_escalation_and_creates_only_customer_role(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());
        $this->seed(RolePermissionSeeder::class);

        $response = $this->post(route('register.store'), [
            'name' => 'Customer', 'email' => 'buyer@example.test', 'password' => 'password', 'password_confirmation' => 'password',
            'roles' => ['super_admin'], 'permissions' => ['users.manage-access'], 'status' => 'suspended',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $user = User::where('email', 'buyer@example.test')->firstOrFail();
        $this->assertSame(['customer'], $user->getRoleNames()->all());
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->can('users.manage-access'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer_registered', 'subject_id' => $user->id]);
        $this->assertDatabaseCount('commission_entries', 0);
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
    }

    public function test_suspended_account_cannot_login(): void
    {
        $user = User::factory()->suspended()->create();

        $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspension_invalidates_an_existing_authenticated_session(): void
    {
        $user = User::factory()->suspended()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertForbidden();
        $this->assertGuest();
    }

    public function test_suspension_between_password_and_two_factor_challenge_is_rejected(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
        $user = User::factory()->suspended()->withTwoFactor()->create();

        $response = $this->withSession(['login.id' => $user->id])->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1']);

        $response->assertForbidden();
        $this->assertGuest();
    }

    public function test_inactive_account_is_rejected_after_an_alternative_login_handler(): void
    {
        $user = User::factory()->suspended()->create();
        Route::middleware('web')->post('/test-alternative-login', function () use ($user) {
            Auth::guard('web')->login($user);

            return response()->noContent();
        });

        $this->post('/test-alternative-login')->assertForbidden();

        $this->assertGuest();
    }

    public function test_user_policy_prevents_reading_another_users_profile(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->assignRole(SystemRole::Partner->value);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $owner));
        $this->assertFalse(Gate::forUser($owner)->allows('view', $other));
        $this->assertFalse(Gate::forUser($owner)->allows('updateAccess', $other));
    }

    public function test_privileged_account_cannot_self_delete(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(SystemRole::SuperAdmin->value);

        $response = $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password']);

        $response->assertForbidden();
        $this->assertModelExists($admin);
    }

    public function test_initial_admin_grant_requires_confirmation_and_is_audited_once(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $this->artisan('honey:bootstrap-admin', ['email' => $user->email])->assertFailed();

        $this->artisan('honey:bootstrap-admin', ['email' => $user->email, '--confirm' => true])->assertSuccessful();
        $this->artisan('honey:bootstrap-admin', ['email' => $user->email, '--confirm' => true])->assertFailed();

        $this->assertTrue($user->fresh()->hasRole(SystemRole::SuperAdmin->value));
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'initial_administrator_granted', 'subject_id' => $user->id]);
    }

    public function test_initial_admin_cannot_be_granted_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('honey:bootstrap-admin', ['email' => 'nobody@example.test', '--confirm' => true])->assertFailed();
    }

    public function test_initial_admin_requires_an_active_verified_user(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->unverified()->create();

        $this->artisan('honey:bootstrap-admin', ['email' => $user->email, '--confirm' => true])->assertFailed();

        $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
