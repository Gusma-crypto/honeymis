<?php

namespace Tests\Feature;

use App\Domains\Shared\Services\StoreSettings;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_save_profile_and_shared_branding_with_an_audit_record(): void
    {
        $this->administrator();
        $this->get(route('admin.commerce-settings'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/commerce-settings')->where('canManageStore', true)->where('storeSettings.revision', 'initial'));

        $this->post(route('admin.store-settings.update'), $this->payload())->assertSessionHasNoErrors()->assertRedirect(route('admin.commerce-settings'));

        $this->assertDatabaseCount('store_settings', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store_settings_updated', 'actor_id' => auth()->id()]);
        $this->get(route('admin.commerce-settings'))->assertInertia(fn (Assert $page) => $page->where('branding.brand_name', 'Madu Nusantara')->where('storeSettings.company_name', 'PT Madu Nusantara'));
        $this->get(route('info.about'))->assertInertia(fn (Assert $page) => $page->where('body', 'Profil perusahaan madu.'));
        $this->get(route('info.contact'))->assertInertia(fn (Assert $page) => $page->where('body', 'admin@example.test · 081234567890 · Bandung'));
    }

    public function test_guest_and_customer_cannot_modify_store_settings(): void
    {
        $this->post(route('admin.store-settings.update'), $this->payload())->assertRedirect(route('login'));
        $customer = User::factory()->create();
        $this->actingAs($customer)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.store-settings.update'), $this->payload())->assertForbidden();

        $this->assertDatabaseCount('store_settings', 0);
    }

    public function test_invalid_fields_and_executable_uploads_do_not_change_settings(): void
    {
        $this->administrator();
        Storage::fake('local');

        $this->post(route('admin.store-settings.update'), [...$this->payload(),
            'brand_name' => '', 'email' => 'invalid', 'website' => 'javascript:alert(1)',
            'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])->assertSessionHasErrors(['brand_name', 'email', 'website', 'logo']);

        $this->assertDatabaseCount('store_settings', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_logo_and_favicon_are_served_and_can_be_removed(): void
    {
        $this->administrator();
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');

        $this->post(route('admin.store-settings.update'), [...$this->payload(), 'logo' => UploadedFile::fake()->createWithContent('logo.png', $png), 'favicon' => UploadedFile::fake()->createWithContent('favicon.png', $png)])
            ->assertSessionHasNoErrors()->assertRedirect();

        $saved = app(StoreSettings::class)->current();
        Storage::disk('local')->assertExists($saved['logo_path']);
        $this->get(route('store.branding', 'logo'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('store.branding', 'favicon'))->assertOk();
        $this->post(route('admin.store-settings.update'), [...$this->payload(), 'revision' => $saved['revision'], 'remove_logo' => true, 'remove_favicon' => true])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($saved['logo_path']);
        Storage::disk('local')->assertMissing($saved['favicon_path']);
        $this->get(route('store.branding', 'logo'))->assertNotFound();
    }

    public function test_stale_update_cannot_overwrite_newer_settings(): void
    {
        $this->administrator();
        $this->post(route('admin.store-settings.update'), $this->payload())->assertSessionHasNoErrors();

        $this->post(route('admin.store-settings.update'), [...$this->payload(), 'brand_name' => 'Stale Brand'])->assertSessionHasErrors('revision');

        $this->assertSame('Madu Nusantara', app(StoreSettings::class)->current()['brand_name']);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_maintenance_blocks_public_access_but_administrator_can_restore_service(): void
    {
        $administrator = $this->administrator();
        $this->post(route('admin.store-settings.update'), [...$this->payload(), 'maintenance_mode' => true])->assertSessionHasNoErrors();
        auth()->logout();

        $this->get(route('catalog.index'))->assertStatus(503)->assertHeader('Retry-After', '300');
        $this->getJson(route('catalog.index'))->assertStatus(503);
        $this->get(route('login'))->assertOk();
        $this->get('/up')->assertOk();
        $this->actingAs($administrator)->get(route('admin.commerce-settings'))->assertOk();
        $this->post(route('admin.store-settings.update'), [...$this->payload(), 'revision' => app(StoreSettings::class)->current()['revision']])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('catalog.index'))->assertOk();
    }

    public function test_disabled_system_blocks_customers_without_locking_out_administrator(): void
    {
        $administrator = $this->administrator();
        $this->post(route('admin.store-settings.update'), [...$this->payload(), 'system_active' => false])->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->create())->get(route('catalog.index'))->assertStatus(503);
        $this->actingAs($administrator)->get(route('admin.commerce-settings'))->assertOk();
    }

    public function test_registration_toggle_prevents_new_accounts(): void
    {
        $this->administrator();
        $this->post(route('admin.store-settings.update'), [...$this->payload(), 'registration_enabled' => false])->assertSessionHasNoErrors();
        auth()->logout();

        $this->get(route('register'))->assertForbidden();
        $this->post(route('register.store'), ['name' => 'New User', 'email' => 'new@example.test', 'password' => 'password', 'password_confirmation' => 'password'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
    }

    private function administrator(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['company_name' => 'PT Madu Nusantara', 'brand_name' => 'Madu Nusantara', 'slogan' => 'Sehat setiap hari',
            'email' => 'admin@example.test', 'phone' => '081234567890', 'website' => 'https://example.test', 'address' => 'Bandung',
            'description' => 'Profil perusahaan madu.', 'payment_instructions' => 'Hubungi rekening resmi toko.',
            'system_active' => true, 'registration_enabled' => true, 'maintenance_mode' => false, 'revision' => 'initial'];
    }
}
