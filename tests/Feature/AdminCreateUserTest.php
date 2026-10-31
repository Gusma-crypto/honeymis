<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_account_with_requested_roles_without_bypassing_email_verification(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.users.store'), $this->payload())->assertRedirect(route('admin.users.index'));
        $user = User::where('email', 'new-user@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('customer'));
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('ExamplePassword!123', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user_access_updated', 'subject_id' => $user->id]);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseCount('commission_entries', 0);
    }

    public function test_store_admin_cannot_create_users(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('store_admin');
        $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.users.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_role_and_verification_override_are_rejected(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.users.store'), [...$this->payload(), 'roles' => ['invented_role'], 'email_verified_at' => now()->toDateTimeString()])->assertSessionHasErrors(['roles.0', 'email_verified_at']);
        $this->assertDatabaseCount('users', 1);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['name' => 'New User', 'email' => 'new-user@example.test', 'password' => 'ExamplePassword!123', 'password_confirmation' => 'ExamplePassword!123', 'roles' => ['customer'], 'idempotency_key' => (string) Str::uuid()];
    }
}
