<?php

namespace Tests\Feature;

use App\Domains\Orders\Models\Customer;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_create_replay_does_not_duplicate_and_edit_preserves_order_snapshot(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('store_admin');
        $data = ['name' => 'Contact Example', 'email' => 'contact@example.test', 'phone' => '081234567890', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.customers.store'), $data)->assertRedirect();
        $this->post(route('admin.customers.store'), $data)->assertRedirect();
        $this->assertDatabaseCount('customers', 1);
        $customer = Customer::firstOrFail();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $snapshot = $order->shipping_address_snapshot;
        $this->put(route('admin.customers.update', $customer), [...$data, 'name' => 'Updated Contact', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('Updated Contact', $customer->fresh()->name);
        $this->assertSame($snapshot, $order->fresh()->shipping_address_snapshot);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_admin_cannot_link_contact_to_a_login_account_from_the_form(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('store_admin');
        $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.customers.store'), ['name' => 'Contact', 'email' => 'contact@example.test', 'user_id' => $actor->id, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_cannot_create_contact_or_override_account_link(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.customers.store'), ['name' => 'Blocked', 'email' => 'blocked@example.test', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
        $this->assertDatabaseCount('customers', 0);
    }
}
