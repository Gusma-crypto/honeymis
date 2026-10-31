<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commission_entries', function (Blueprint $table): void {
            $table->string('commission_type')->default('direct')->after('level');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['partner_referral_id', 'placed_at'], 'orders_partner_placed_idx');
        });

        Schema::create('mlm_program_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedSmallInteger('referral_first_order_bps')->default(500);
            $table->json('generation_rates');
            $table->json('rank_requirements');
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        Schema::create('partner_monthly_ranks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            $table->date('period_start');
            $table->string('rank');
            $table->bigInteger('personal_sales')->default(0);
            $table->bigInteger('team_sales')->default(0);
            $table->timestampTz('calculated_at');
            $table->timestampsTz();
            $table->unique(['partner_id', 'period_start']);
            $table->index(['period_start', 'rank']);
        });

        DB::table('mlm_program_settings')->insert([
            'id' => 1,
            'version' => 1,
            'referral_first_order_bps' => 500,
            'generation_rates' => json_encode([400, 200, 100, 50, 50], JSON_THROW_ON_ERROR),
            'rank_requirements' => json_encode([
                'Member' => ['personal_sales' => 0, 'team_sales' => 0],
                'Bronze' => ['personal_sales' => 1_000_000, 'team_sales' => 3_000_000],
                'Silver' => ['personal_sales' => 3_000_000, 'team_sales' => 10_000_000],
                'Gold' => ['personal_sales' => 7_500_000, 'team_sales' => 25_000_000],
                'Platinum' => ['personal_sales' => 15_000_000, 'team_sales' => 60_000_000],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_invariants');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_invariants CHECK (subtotal >= 0 AND discount_total >= 0 AND discount_total <= subtotal AND shipping_total >= 0 AND tax_total >= 0 AND grand_total = subtotal - discount_total + shipping_total + tax_total AND currency = 'IDR' AND status IN ('pending_payment','paid','processing','shipped','delivered','completed','cancelled','refunded') AND ((partner_referral_id IS NULL AND commission_rule_id IS NULL) OR (partner_referral_id IS NOT NULL AND referral_snapshot IS NOT NULL AND rule_snapshot IS NOT NULL)))");
            DB::statement('ALTER TABLE commission_entries DROP CONSTRAINT commission_entries_invariants');
            DB::table('commission_entries')->update(['level' => 0]);
            DB::statement("ALTER TABLE commission_entries ADD CONSTRAINT commission_entries_invariants CHECK (((commission_type = 'direct' AND level = 0) OR (commission_type = 'referral' AND level = 1) OR (commission_type = 'network' AND level BETWEEN 1 AND 5)) AND base_amount > 0 AND amount > 0 AND amount <= base_amount AND status IN ('pending','available','reserved','paid_out','cancelled','reversed'))");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $maria = str_contains((string) DB::scalar('select version()'), 'MariaDB');
            DB::statement('ALTER TABLE `orders` DROP '.($maria ? 'CONSTRAINT' : 'CHECK').' `honey_orders_check`');
            DB::statement("ALTER TABLE `orders` ADD CONSTRAINT `honey_orders_check` CHECK (subtotal >= 0 AND discount_total >= 0 AND discount_total <= subtotal AND shipping_total >= 0 AND tax_total >= 0 AND grand_total = subtotal - discount_total + shipping_total + tax_total AND currency = 'IDR' AND status IN ('pending_payment','paid','processing','shipped','delivered','completed','cancelled','refunded') AND ((partner_referral_id IS NULL AND commission_rule_id IS NULL) OR (partner_referral_id IS NOT NULL AND referral_snapshot IS NOT NULL AND rule_snapshot IS NOT NULL)))");
            DB::statement('ALTER TABLE `commission_entries` DROP '.($maria ? 'CONSTRAINT' : 'CHECK').' `honey_commission_entries_check`');
            DB::table('commission_entries')->update(['level' => 0]);
            DB::statement("ALTER TABLE `commission_entries` ADD CONSTRAINT `honey_commission_entries_check` CHECK (((commission_type = 'direct' AND level = 0) OR (commission_type = 'referral' AND level = 1) OR (commission_type = 'network' AND level BETWEEN 1 AND 5)) AND base_amount > 0 AND amount > 0 AND amount <= base_amount AND status IN ('pending','available','reserved','paid_out','cancelled','reversed'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('commission_entries')->where('level', '!=', 0)->orWhere('commission_type', '!=', 'direct')->exists()
            || DB::table('orders')->whereNotNull('partner_referral_id')->whereNull('commission_rule_id')->exists()) {
            throw new RuntimeException('Cannot roll back MLM support while multi-level commission entries exist.');
        }

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_invariants');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_invariants CHECK (subtotal >= 0 AND discount_total >= 0 AND discount_total <= subtotal AND shipping_total >= 0 AND tax_total >= 0 AND grand_total = subtotal - discount_total + shipping_total + tax_total AND currency = 'IDR' AND status IN ('pending_payment','paid','processing','shipped','delivered','completed','cancelled','refunded') AND ((partner_referral_id IS NULL AND commission_rule_id IS NULL) OR (partner_referral_id IS NOT NULL AND commission_rule_id IS NOT NULL AND referral_snapshot IS NOT NULL AND rule_snapshot IS NOT NULL)))");
            DB::statement('ALTER TABLE commission_entries DROP CONSTRAINT commission_entries_invariants');
            DB::table('commission_entries')->update(['level' => 1]);
            DB::statement("ALTER TABLE commission_entries ADD CONSTRAINT commission_entries_invariants CHECK (level = 1 AND base_amount > 0 AND amount > 0 AND amount <= base_amount AND status IN ('pending','available','reserved','paid_out','cancelled','reversed'))");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $maria = str_contains((string) DB::scalar('select version()'), 'MariaDB');
            DB::statement('ALTER TABLE `orders` DROP '.($maria ? 'CONSTRAINT' : 'CHECK').' `honey_orders_check`');
            DB::statement("ALTER TABLE `orders` ADD CONSTRAINT `honey_orders_check` CHECK (subtotal >= 0 AND discount_total >= 0 AND discount_total <= subtotal AND shipping_total >= 0 AND tax_total >= 0 AND grand_total = subtotal - discount_total + shipping_total + tax_total AND currency = 'IDR' AND status IN ('pending_payment','paid','processing','shipped','delivered','completed','cancelled','refunded') AND ((partner_referral_id IS NULL AND commission_rule_id IS NULL) OR (partner_referral_id IS NOT NULL AND commission_rule_id IS NOT NULL AND referral_snapshot IS NOT NULL AND rule_snapshot IS NOT NULL)))");
            DB::statement('ALTER TABLE `commission_entries` DROP '.($maria ? 'CONSTRAINT' : 'CHECK').' `honey_commission_entries_check`');
            DB::table('commission_entries')->update(['level' => 1]);
            DB::statement("ALTER TABLE `commission_entries` ADD CONSTRAINT `honey_commission_entries_check` CHECK (level = 1 AND base_amount > 0 AND amount > 0 AND amount <= base_amount AND status IN ('pending','available','reserved','paid_out','cancelled','reversed'))");
        }

        Schema::dropIfExists('partner_monthly_ranks');
        Schema::dropIfExists('mlm_program_settings');
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index('partner_referral_id', 'orders_partner_referral_idx');
            });
        }
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_partner_placed_idx');
        });
        Schema::table('commission_entries', function (Blueprint $table): void {
            $table->dropColumn('commission_type');
        });
    }
};
