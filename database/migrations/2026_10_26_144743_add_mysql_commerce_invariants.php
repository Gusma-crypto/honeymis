<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        $version = (string) DB::scalar('select version()');
        $maria = str_contains($version, 'MariaDB');
        preg_match($maria ? '/(\d+\.\d+\.\d+)(?=-MariaDB)/i' : '/\d+\.\d+\.\d+/', $version, $matches);
        $number = $maria ? ($matches[1] ?? null) : ($matches[0] ?? null);
        if ($number === null || version_compare($number, $maria ? '10.4.0' : '8.0.16', '<')) {
            throw new RuntimeException('Enforced CHECK constraints require MySQL >= 8.0.16 or MariaDB >= 10.4.');
        }
        foreach ($this->checks() as $table => $expression) {
            if (DB::table($table)->whereRaw('NOT ('.$expression.')')->exists()) {
                throw new RuntimeException('Existing rows violate commerce invariants in '.$table.'. No data was rewritten.');
            }
        }
        foreach ($this->checks() as $table => $expression) {
            $name = 'honey_'.$table.'_check';
            if (! $this->constraintExists($table, $name)) {
                DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK ($expression)");
            }
        }
        if (! Schema::hasColumn('payments', 'verified_order_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->unsignedBigInteger('verified_order_id')->nullable()->storedAs("CASE WHEN status IN ('verified', 'refunded') THEN order_id ELSE NULL END");
            });
        }
        if (! Schema::hasIndex('payments', 'payments_one_verified_order_unique')) {
            Schema::table('payments', fn (Blueprint $table) => $table->unique('verified_order_id', 'payments_one_verified_order_unique'));
        }
        foreach (['commission_entries' => 'beneficiary_partner_id', 'payout_requests' => 'partner_id'] as $table => $owner) {
            $name = 'honey_'.$table.'_owner_unique';
            if (! Schema::hasIndex($table, $name)) {
                DB::statement("ALTER TABLE `$table` ADD UNIQUE `$name` (id, `$owner`)");
            }
        }
        if (! $this->constraintExists('wallet_ledger_entries', 'honey_ledger_commission_owner')) {
            DB::statement('ALTER TABLE wallet_ledger_entries ADD CONSTRAINT honey_ledger_commission_owner FOREIGN KEY (commission_entry_id, partner_id) REFERENCES commission_entries (id, beneficiary_partner_id)');
        }
        if (! $this->constraintExists('wallet_ledger_entries', 'honey_ledger_payout_owner')) {
            DB::statement('ALTER TABLE wallet_ledger_entries ADD CONSTRAINT honey_ledger_payout_owner FOREIGN KEY (payout_id, partner_id) REFERENCES payout_requests (id, partner_id)');
        }
        foreach ($this->triggers() as $name => $body) {
            if (! DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', DB::connection()->getDatabaseName())->where('TRIGGER_NAME', $name)->exists()) {
                DB::unprepared("CREATE TRIGGER `$name` $body");
            }
        }
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        foreach (array_keys($this->triggers()) as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS `$name`");
        }
        DB::statement('ALTER TABLE wallet_ledger_entries DROP FOREIGN KEY honey_ledger_commission_owner');
        DB::statement('ALTER TABLE wallet_ledger_entries DROP FOREIGN KEY honey_ledger_payout_owner');
        DB::statement('ALTER TABLE commission_entries DROP INDEX honey_commission_entries_owner_unique');
        DB::statement('ALTER TABLE payout_requests DROP INDEX honey_payout_requests_owner_unique');
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_one_verified_order_unique');
            $table->dropColumn('verified_order_id');
        });
        $maria = str_contains((string) DB::scalar('select version()'), 'MariaDB');
        foreach (array_keys($this->checks()) as $table) {
            $name = 'honey_'.$table.'_check';
            DB::statement("ALTER TABLE `$table` DROP ".($maria ? 'CONSTRAINT' : 'CHECK')." `$name`");
        }
    }

    private function constraintExists(string $table, string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)->where('CONSTRAINT_NAME', $name)->exists();
    }

    /** @return array<literal-string, literal-string> */
    private function checks(): array
    {
        return [
            'products' => "weight_g > 0 AND status IN ('draft','active','inactive')",
            'categories' => "status IN ('draft','active','inactive')",
            'product_variants' => "price >= 0 AND cost >= 0 AND status IN ('draft','active','inactive') AND discount_amount >= 0 AND discount_amount <= price",
            'partners' => "status IN ('pending','active','suspended','rejected')",
            'inventory_balances' => 'on_hand >= 0 AND reserved >= 0 AND reserved <= on_hand',
            'inventory_movements' => 'quantity_delta <> 0 OR reserved_delta <> 0',
            'cart_items' => 'quantity > 0',
            'commission_rules' => "version > 0 AND level = 1 AND strategy = 'direct_v1' AND rate_bps BETWEEN 0 AND 10000 AND fixed_amount >= 0 AND cap_amount >= 0 AND NOT (product_id IS NOT NULL AND category_id IS NOT NULL) AND NOT (rate_bps > 0 AND fixed_amount > 0) AND (valid_until IS NULL OR valid_until > valid_from) AND status IN ('draft','published','retired') AND (status = 'draft' OR (approved_by IS NOT NULL AND approved_at IS NOT NULL))",
            'orders' => "subtotal >= 0 AND discount_total >= 0 AND discount_total <= subtotal AND shipping_total >= 0 AND tax_total >= 0 AND grand_total = subtotal - discount_total + shipping_total + tax_total AND currency = 'IDR' AND status IN ('pending_payment','paid','processing','shipped','delivered','completed','cancelled','refunded') AND ((partner_referral_id IS NULL AND commission_rule_id IS NULL) OR (partner_referral_id IS NOT NULL AND commission_rule_id IS NOT NULL AND referral_snapshot IS NOT NULL AND rule_snapshot IS NOT NULL))",
            'order_items' => 'quantity > 0 AND unit_price_snapshot >= 0 AND cost_snapshot >= 0 AND discount_snapshot >= 0 AND discount_snapshot <= unit_price_snapshot * quantity',
            'payments' => "amount > 0 AND status IN ('pending','submitted','verified','rejected','refunded') AND (status <> 'verified' OR (verified_by IS NOT NULL AND verified_at IS NOT NULL))",
            'refunds' => "amount > 0 AND status IN ('requested','approved','rejected','processed')",
            'commission_entries' => "level = 1 AND base_amount > 0 AND amount > 0 AND amount <= base_amount AND status IN ('pending','available','reserved','paid_out','cancelled','reversed')",
            'payout_requests' => "amount > 0 AND status IN ('requested','approved','rejected','paid','failed') AND (status NOT IN ('approved','paid') OR (approved_by IS NOT NULL AND approved_at IS NOT NULL)) AND (status <> 'paid' OR (paid_at IS NOT NULL AND proof_storage_key IS NOT NULL AND transfer_reference IS NOT NULL))",
            'wallet_ledger_entries' => "amount_signed <> 0 AND currency = 'IDR' AND bucket IN ('pending','available','reserved','paid','receivable') AND entry_type IN ('commission','release','reserve','payout','reversal','offset') AND (commission_entry_id IS NOT NULL OR payout_id IS NOT NULL OR refund_id IS NOT NULL)",
        ];
    }

    /** @return array<literal-string, literal-string> */
    private function triggers(): array
    {
        return [
            'honey_wallet_no_update' => "BEFORE UPDATE ON wallet_ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Wallet ledger is append-only'",
            'honey_wallet_no_delete' => "BEFORE DELETE ON wallet_ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Wallet ledger is append-only'",
            'honey_audit_no_update' => "BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit history is append-only'",
            'honey_audit_no_delete' => "BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit history is append-only'",
            'honey_rule_immutable' => "BEFORE UPDATE ON commission_rules FOR EACH ROW BEGIN IF OLD.status <> 'draft' AND (NEW.status = 'draft' OR NOT (NEW.version <=> OLD.version) OR NOT (NEW.strategy <=> OLD.strategy) OR NOT (NEW.level <=> OLD.level) OR NOT (NEW.product_id <=> OLD.product_id) OR NOT (NEW.category_id <=> OLD.category_id) OR NOT (NEW.rate_bps <=> OLD.rate_bps) OR NOT (NEW.fixed_amount <=> OLD.fixed_amount) OR NOT (NEW.cap_amount <=> OLD.cap_amount) OR NOT (NEW.valid_from <=> OLD.valid_from) OR NOT (NEW.valid_until <=> OLD.valid_until) OR NOT (NEW.approved_by <=> OLD.approved_by) OR NOT (NEW.approved_at <=> OLD.approved_at) OR NOT (NEW.created_at <=> OLD.created_at)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Published rule versions are immutable'; END IF; END",
            'honey_sponsor_immutable' => "BEFORE UPDATE ON partners FOR EACH ROW BEGIN IF OLD.approved_at IS NOT NULL AND NOT (NEW.sponsor_partner_id <=> OLD.sponsor_partner_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Approved sponsor is immutable'; END IF; IF NEW.sponsor_partner_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partner cannot sponsor itself'; END IF; END",
            'honey_sponsor_self_insert' => "AFTER INSERT ON partners FOR EACH ROW BEGIN IF NEW.sponsor_partner_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Partner cannot sponsor itself'; END IF; END",
            'honey_category_self_insert' => "AFTER INSERT ON categories FOR EACH ROW BEGIN IF NEW.parent_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category cannot parent itself'; END IF; END",
            'honey_category_parent' => "BEFORE UPDATE ON categories FOR EACH ROW BEGIN IF NEW.parent_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category cannot parent itself'; END IF; END",
        ];
    }
};
