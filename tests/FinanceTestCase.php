<?php

namespace Tests;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commissions\Actions\ManageCommissionRule;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Commissions\Services\CommissionEngine;
use App\Domains\Orders\Actions\ManageOrder;
use App\Domains\Orders\Actions\PlaceOrder;
use App\Domains\Orders\Actions\SubmitPaymentProof;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\CartManager;
use App\Domains\Orders\Services\CheckoutQuote;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\ReferralCapture;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class FinanceTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $finance;

    protected User $store;

    protected Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Storage::fake('local');
        Notification::fake();
        config(['partners.minimum_payout_idr' => 1000]);
    }

    protected function financeActors(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->finance = User::factory()->create();
        $this->finance->assignRole('finance');
        $this->store = User::factory()->create();
        $this->store->assignRole('store_admin');
        $this->partner = Partner::factory()->active()->create();
        $this->partner->user->assignRole('partner');
    }

    /** @param array<string, mixed> $ruleAttributes */
    protected function commissionOrder(bool $mature = false, ?User $buyer = null, bool $eligible = true, bool $publishRule = true, array $ruleAttributes = [], int $quantity = 1, ?Partner $referralPartner = null): Order
    {
        $buyer ??= User::factory()->create();
        $referralPartner ??= $this->partner;
        $variant = ProductVariant::factory()->withStock(max(10, $quantity))->create(['price' => 100000, 'cost' => 60000, 'discount_amount' => 0]);
        $variant->product->update(['commission_eligible' => $eligible]);
        $rule = CommissionRule::factory()->create($ruleAttributes);
        if ($publishRule) {
            app(ManageCommissionRule::class)->publish($this->finance, $rule);
        }
        $token = app(ReferralCapture::class)->capture($referralPartner->referral_code, null, $buyer->id);
        app(CartManager::class)->setQuantity($buyer, $variant->id, $quantity);
        $quote = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($buyer));
        $order = app(PlaceOrder::class)->handle($buyer, ['recipient' => 'Buyer', 'phone' => '081234567890', 'province' => 'Jawa Barat', 'city' => 'Bandung', 'district' => 'Coblong', 'postal_code' => '40132', 'address' => 'Alamat uji 123'], $quote['fingerprint'], (string) Str::uuid(), $token);
        app(SubmitPaymentProof::class)->handle($buyer, $order, UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'), (string) Str::uuid());
        app(ManageOrder::class)->handle($this->finance, $order, 'verify', (string) Str::uuid());
        app(CommissionEngine::class)->accrue($order->id);
        if ($mature) {
            app(ManageOrder::class)->handle($this->store, $order, 'process', (string) Str::uuid());
            app(ManageOrder::class)->handle($this->store, $order, 'ship', (string) Str::uuid(), ['courier' => 'Test', 'tracking_number' => (string) Str::uuid()]);
            app(ManageOrder::class)->handle($this->store, $order, 'deliver', (string) Str::uuid());
            $this->travel(8)->days();
            app(CommissionEngine::class)->release($order->id);
        }

        return $order->fresh();
    }

    /** @return array{bank_name: string, account_name: string, account_number: string} */
    protected function bank(): array
    {
        return ['bank_name' => 'Bank Test', 'account_name' => 'Partner Test', 'account_number' => '1234567890'];
    }
}
