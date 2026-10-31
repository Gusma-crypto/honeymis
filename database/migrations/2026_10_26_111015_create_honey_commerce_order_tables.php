<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('idempotency_key')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('partner_referral_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->restrictOnDelete();
            $table->jsonb('referral_snapshot')->nullable();
            $table->jsonb('rule_snapshot')->nullable();
            $table->jsonb('shipping_address_snapshot');
            $table->bigInteger('subtotal');
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('shipping_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('grand_total');
            $table->char('currency', 3)->default('IDR');
            $table->string('status')->default('pending_payment');
            $table->timestampTz('placed_at');
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();
            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('sku_snapshot');
            $table->string('name_snapshot');
            $table->jsonb('attributes_snapshot');
            $table->integer('quantity');
            $table->bigInteger('unit_price_snapshot');
            $table->bigInteger('discount_snapshot')->default(0);
            $table->bigInteger('cost_snapshot');
            $table->boolean('commission_eligible_snapshot')->default(false);
            $table->timestampsTz();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('provider')->default('manual');
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->bigInteger('amount');
            $table->string('status')->default('pending');
            $table->string('proof_storage_key')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();
            $table->unique(['provider', 'provider_reference']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('courier');
            $table->string('tracking_number')->nullable();
            $table->string('status')->default('pending');
            $table->timestampTz('shipped_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->restrictOnDelete();
            $table->string('idempotency_key')->unique();
            $table->bigInteger('amount');
            $table->text('reason');
            $table->string('status')->default('requested');
            $table->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
