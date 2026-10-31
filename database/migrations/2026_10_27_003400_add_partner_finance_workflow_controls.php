<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('fraud_hold')->default(false);
            $table->timestampTz('commission_checked_at')->nullable();
            $table->smallInteger('return_window_days')->default(7);
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('commission_rule_id')->nullable()->constrained('commission_rules')->restrictOnDelete();
            $table->json('commission_snapshot')->nullable();
        });
        Schema::table('commission_entries', function (Blueprint $table): void {
            $table->bigInteger('reversed_amount')->default(0);
            $table->bigInteger('offset_amount')->default(0);
        });
        Schema::table('payout_requests', function (Blueprint $table): void {
            $table->string('request_fingerprint', 64)->nullable();
            $table->string('bank_last4', 4)->nullable();
        });
        Schema::table('refunds', fn (Blueprint $table) => $table->string('request_fingerprint', 64)->nullable());
        Schema::create('payout_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payout_id')->constrained('payout_requests')->restrictOnDelete();
            $table->foreignId('commission_entry_id')->constrained('commission_entries')->restrictOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->unique(['payout_id', 'commission_entry_id']);
        });
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE commission_rules MODIFY valid_from DATETIME NOT NULL');
            DB::statement('ALTER TABLE payout_requests MODIFY requested_at DATETIME NOT NULL');
            DB::statement('ALTER TABLE orders MODIFY placed_at DATETIME NOT NULL');
            DB::statement('ALTER TABLE referral_attributions MODIFY expires_at DATETIME NOT NULL');
            DB::statement('ALTER TABLE carts MODIFY expires_at DATETIME NOT NULL');
            DB::statement('ALTER TABLE outbox_messages MODIFY available_at DATETIME NOT NULL');
            DB::statement('ALTER TABLE commission_entries ADD CONSTRAINT honey_commission_adjustment CHECK (reversed_amount >= 0 AND offset_amount >= 0 AND reversed_amount <= amount AND offset_amount <= amount)');
            DB::statement('ALTER TABLE payout_allocations ADD CONSTRAINT honey_allocation_amount CHECK (amount > 0)');
            DB::statement('ALTER TABLE payout_allocations ADD CONSTRAINT honey_allocation_payout_owner FOREIGN KEY (payout_id, partner_id) REFERENCES payout_requests (id, partner_id)');
            DB::statement('ALTER TABLE payout_allocations ADD CONSTRAINT honey_allocation_commission_owner FOREIGN KEY (commission_entry_id, partner_id) REFERENCES commission_entries (id, beneficiary_partner_id)');
            DB::statement('ALTER TABLE orders DROP '.(str_contains((string) DB::scalar('select version()'), 'MariaDB') ? 'CONSTRAINT' : 'CHECK').' honey_orders_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT honey_orders_check CHECK (subtotal >= 0 AND discount_total >= 0 AND discount_total <= subtotal AND shipping_total >= 0 AND tax_total >= 0 AND grand_total = subtotal - discount_total + shipping_total + tax_total AND currency = 'IDR' AND status IN ('pending_payment','paid','processing','shipped','delivered','completed','cancelled','refunded') AND ((partner_referral_id IS NULL AND commission_rule_id IS NULL) OR (partner_referral_id IS NOT NULL AND referral_snapshot IS NOT NULL AND rule_snapshot IS NOT NULL)))");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            if (DB::table('orders')->whereNotNull('partner_referral_id')->whereNull('commission_rule_id')->exists()) {
                throw new RuntimeException('Rollback cannot discard attribution without an applicable rule. Restore a reviewed backup instead.');
            }
            DB::statement('ALTER TABLE orders DROP '.(str_contains((string) DB::scalar('select version()'), 'MariaDB') ? 'CONSTRAINT' : 'CHECK').' honey_orders_check');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT honey_orders_check CHECK (subtotal >= 0 AND discount_total >= 0 AND discount_total <= subtotal AND shipping_total >= 0 AND tax_total >= 0 AND grand_total = subtotal - discount_total + shipping_total + tax_total AND currency = 'IDR' AND status IN ('pending_payment','paid','processing','shipped','delivered','completed','cancelled','refunded') AND ((partner_referral_id IS NULL AND commission_rule_id IS NULL) OR (partner_referral_id IS NOT NULL AND commission_rule_id IS NOT NULL AND referral_snapshot IS NOT NULL AND rule_snapshot IS NOT NULL)))");
        }
        Schema::dropIfExists('payout_allocations');
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE commission_entries DROP '.(str_contains((string) DB::scalar('select version()'), 'MariaDB') ? 'CONSTRAINT' : 'CHECK').' honey_commission_adjustment');
        }
        Schema::table('refunds', fn (Blueprint $table) => $table->dropColumn('request_fingerprint'));
        Schema::table('payout_requests', fn (Blueprint $table) => $table->dropColumn(['request_fingerprint', 'bank_last4']));
        Schema::table('commission_entries', fn (Blueprint $table) => $table->dropColumn(['reversed_amount', 'offset_amount']));
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('commission_rule_id');
            $table->dropColumn('commission_snapshot');
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['fraud_hold', 'commission_checked_at', 'return_window_days']));
    }
};
