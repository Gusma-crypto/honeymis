<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CatalogManagementController;
use App\Http\Controllers\Admin\CommissionRuleController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderManagementController;
use App\Http\Controllers\Admin\PartnerManagementController;
use App\Http\Controllers\Admin\PayoutManagementController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ShippingSettingsController;
use App\Http\Controllers\Admin\UserAccessController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\StoreInfoController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';

Route::middleware(['auth', 'verified', 'password.confirm', 'throttle:30,1'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('users', [UserAccessController::class, 'index'])->name('users.index');
    Route::post('users', [UserAccessController::class, 'store'])->name('users.store');
    Route::patch('users/{user}/access', [UserAccessController::class, 'update'])->name('users.access.update');
});

Route::get('products', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('products/{product:slug}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('product-images/{image}', [CatalogController::class, 'image'])->name('catalog.image');

Route::middleware(['auth', 'verified', 'throttle:60,1'])->group(function (): void {
    Route::get('cart', [CartController::class, 'index'])->name('cart.index');
    Route::put('cart', [CartController::class, 'update'])->name('cart.update');
    Route::get('shipping/destinations', [ShippingController::class, 'destinations'])->name('shipping.destinations')->middleware('throttle:20,1');
    Route::post('shipping/quotes', [ShippingController::class, 'quotes'])->name('shipping.quotes')->middleware('throttle:10,1');
    Route::get('checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/proof', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payment-proofs/{payment}', [PaymentController::class, 'show'])->name('payments.proof');
    Route::patch('orders/{order}/status', [OrderController::class, 'update'])->name('orders.update')->middleware('password.confirm');
});

Route::middleware(['auth', 'verified', 'password.confirm', 'throttle:60,1'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('catalog', [CatalogManagementController::class, 'index'])->name('catalog.index');
    Route::post('catalog', [CatalogManagementController::class, 'store'])->name('catalog.store');
    Route::put('catalog/{product}', [CatalogManagementController::class, 'update'])->name('catalog.update');
    Route::post('catalog/{product}/image', [CatalogManagementController::class, 'image'])->name('catalog.image');
    Route::post('categories', [CatalogManagementController::class, 'category'])->name('categories.store');
    Route::put('categories/{category}', [CatalogManagementController::class, 'category'])->name('categories.update');
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/{variant}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::get('orders', [OrderManagementController::class, 'index'])->name('orders.index');
});

Route::get('r/{code}', [PartnerController::class, 'referral'])->name('referral.visit')->middleware('throttle:60,1');
Route::middleware(['auth', 'verified', 'throttle:60,1'])->group(function (): void {
    Route::get('partner', [PartnerController::class, 'index'])->name('partner.dashboard');
    Route::post('partner', [PartnerController::class, 'store'])->name('partner.apply');
    Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
    Route::post('payouts', [PayoutController::class, 'store'])->name('payouts.store')->middleware('password.confirm');
    Route::get('payouts/{payout}/proof', [PayoutController::class, 'proof'])->name('payouts.proof');
    Route::post('orders/{order}/refund-request', [OrderController::class, 'refund'])->name('orders.refund');
});
Route::middleware(['auth', 'verified', 'password.confirm', 'throttle:60,1'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('partners', [PartnerManagementController::class, 'index'])->name('partners.index');
    Route::patch('partners/{partner}', [PartnerManagementController::class, 'update'])->name('partners.update');
    Route::get('commission-rules', [CommissionRuleController::class, 'index'])->name('commission-rules.index');
    Route::post('commission-rules', [CommissionRuleController::class, 'store'])->name('commission-rules.store');
    Route::post('commission-rules/{rule}/publish', [CommissionRuleController::class, 'publish'])->name('commission-rules.publish');
    Route::get('commission-rules/{rule}/preview', [CommissionRuleController::class, 'preview'])->name('commission-rules.preview');
    Route::post('commission-rules/{rule}/retire', [CommissionRuleController::class, 'retire'])->name('commission-rules.retire');
    Route::get('payouts', [PayoutManagementController::class, 'index'])->name('payouts.index');
    Route::post('payouts/{payout}', [PayoutManagementController::class, 'update'])->name('payouts.update');
    Route::get('refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::post('orders/{order}/refunds', [RefundController::class, 'store'])->name('refunds.store');
    Route::post('refunds/{refund}', [RefundController::class, 'decide'])->name('refunds.decide');
    Route::patch('orders/{order}/hold', [RefundController::class, 'hold'])->name('orders.hold');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
});

foreach (['about', 'contact', 'faq', 'privacy', 'terms', 'returns'] as $page) {
    Route::get($page, [StoreInfoController::class, 'show'])->defaults('page', $page)->name('info.'.$page);
}
Route::middleware(['auth', 'verified', 'password.confirm'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('settings', [StoreInfoController::class, 'settings'])->name('commerce-settings');
    Route::put('shipping-settings', [ShippingSettingsController::class, 'update'])->name('shipping-settings.update')->middleware('throttle:20,1');
    Route::get('shipping-settings/destinations', [ShippingSettingsController::class, 'destinations'])->name('shipping-settings.destinations')->middleware('throttle:20,1');
});
