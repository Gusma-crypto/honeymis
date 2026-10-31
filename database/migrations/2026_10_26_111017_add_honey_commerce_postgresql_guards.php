<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE products ADD CONSTRAINT products_invariants CHECK (weight_g > 0 AND status IN ('draft','active','inactive'))");
        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_invariants CHECK (status IN ('draft','active','inactive') AND (parent_id IS NULL OR parent_id <> id))");
        DB::statement("ALTER TABLE product_variants ADD CONSTRAINT product_variants_invariants CHECK (price >= 0 AND cost >= 0 AND status IN ('draft','active','inactive'))");
        DB::statement("ALTER TABLE partners ADD CONSTRAINT partners_invariants CHECK (status IN ('pending','active','suspended','rejected') AND (sponsor_partner_id IS NULL OR sponsor_partner_id <> id))");
        DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_balances_invariants CHECK (on_hand >= 0 AND reserved >= 0 AND reserved <= on_hand)');
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_invariants CHECK (quantity_delta <> 0 OR reserved_delta <> 0)');
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_invariants CHECK (quantity > 0)');
        DB::statement("ALTER TABLE commission_rules ADD CONSTRAINT commission_rules_invariants CHECK (version > 0 AND level = 1 AND strategy = 'direct_v1' AND rate_bps BETWEEN 0 AND 10000 AND fixed_amount >= 0 AND cap_amount >= 0 AND NOT (product_id IS NOT NULL AND category_id IS NOT NULL) AND NOT (rate_bps > 0 AND fixed_amount > 0) AND (valid_until IS NULL OR valid_until > valid_from) AND status IN ('draft','published','retired') AND (status = 'draft' OR (approved_by IS NOT NULL AND approved_at IS NOT NULL)))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_invariants CHECK (subtotal >= 0 AND discount_total >= 0 AND discount_total <= subtotal AND shipping_total >= 0 AND tax_total >= 0 AND grand_total = subtotal - discount_total + shipping_total + tax_total AND currency = 'IDR' AND status IN ('pending_payment','paid','processing','shipped','delivered','completed','cancelled','refunded') AND ((partner_referral_id IS NULL AND commission_rule_id IS NULL) OR (partner_referral_id IS NOT NULL AND commission_rule_id IS NOT NULL AND referral_snapshot IS NOT NULL AND rule_snapshot IS NOT NULL)))");
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_invariants CHECK (quantity > 0 AND unit_price_snapshot >= 0 AND cost_snapshot >= 0 AND discount_snapshot >= 0 AND discount_snapshot <= unit_price_snapshot * quantity)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_invariants CHECK (amount > 0 AND status IN ('pending','submitted','verified','rejected','refunded') AND (status <> 'verified' OR (verified_by IS NOT NULL AND verified_at IS NOT NULL)))");
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_invariants CHECK (amount > 0 AND status IN ('requested','approved','rejected','processed'))");
        DB::statement("ALTER TABLE commission_entries ADD CONSTRAINT commission_entries_invariants CHECK (level = 1 AND base_amount > 0 AND amount > 0 AND amount <= base_amount AND status IN ('pending','available','reserved','paid_out','cancelled','reversed'))");
        DB::statement("ALTER TABLE payout_requests ADD CONSTRAINT payout_requests_invariants CHECK (amount > 0 AND status IN ('requested','approved','rejected','paid','failed') AND (status NOT IN ('approved','paid') OR (approved_by IS NOT NULL AND approved_at IS NOT NULL)) AND (status <> 'paid' OR (paid_at IS NOT NULL AND proof_storage_key IS NOT NULL AND transfer_reference IS NOT NULL)))");
        DB::statement("ALTER TABLE wallet_ledger_entries ADD CONSTRAINT wallet_ledger_entries_invariants CHECK (amount_signed <> 0 AND currency = 'IDR' AND bucket IN ('pending','available','reserved','paid','receivable') AND entry_type IN ('commission','release','reserve','payout','reversal','offset') AND (commission_entry_id IS NOT NULL OR payout_id IS NOT NULL OR refund_id IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX one_verified_payment_per_order ON payments (order_id) WHERE status IN ('verified', 'refunded')");
        DB::statement('ALTER TABLE commission_entries ADD CONSTRAINT commission_owner_unique UNIQUE (id, beneficiary_partner_id)');
        DB::statement('ALTER TABLE payout_requests ADD CONSTRAINT payout_owner_unique UNIQUE (id, partner_id)');
        DB::statement('ALTER TABLE wallet_ledger_entries ADD CONSTRAINT ledger_commission_owner FOREIGN KEY (commission_entry_id, partner_id) REFERENCES commission_entries (id, beneficiary_partner_id)');
        DB::statement('ALTER TABLE wallet_ledger_entries ADD CONSTRAINT ledger_payout_owner FOREIGN KEY (payout_id, partner_id) REFERENCES payout_requests (id, partner_id)');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION honey_prevent_history_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Financial and audit history is append-only';
END;
$$;
CREATE TRIGGER wallet_append_only BEFORE UPDATE OR DELETE ON wallet_ledger_entries FOR EACH ROW EXECUTE FUNCTION honey_prevent_history_mutation();
CREATE TRIGGER audit_append_only BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION honey_prevent_history_mutation();
CREATE OR REPLACE FUNCTION honey_protect_rule_version() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.status <> 'draft' AND (to_jsonb(NEW) - 'status' - 'updated_at') IS DISTINCT FROM (to_jsonb(OLD) - 'status' - 'updated_at') THEN
        RAISE EXCEPTION 'Published rule versions are immutable';
    END IF;
    IF OLD.status <> 'draft' AND NEW.status = 'draft' THEN
        RAISE EXCEPTION 'Published rule cannot return to draft';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER commission_rule_immutable BEFORE UPDATE ON commission_rules FOR EACH ROW EXECUTE FUNCTION honey_protect_rule_version();
CREATE OR REPLACE FUNCTION honey_protect_sponsor() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.approved_at IS NOT NULL AND NEW.sponsor_partner_id IS DISTINCT FROM OLD.sponsor_partner_id THEN
        RAISE EXCEPTION 'Approved sponsor is immutable';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER sponsor_immutable BEFORE UPDATE ON partners FOR EACH ROW EXECUTE FUNCTION honey_protect_sponsor();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER sponsor_immutable ON partners;
DROP FUNCTION honey_protect_sponsor();
DROP TRIGGER commission_rule_immutable ON commission_rules;
DROP FUNCTION honey_protect_rule_version();
DROP TRIGGER audit_append_only ON audit_logs;
DROP TRIGGER wallet_append_only ON wallet_ledger_entries;
DROP FUNCTION honey_prevent_history_mutation();
SQL);
        DB::statement('ALTER TABLE wallet_ledger_entries DROP CONSTRAINT ledger_commission_owner');
        DB::statement('ALTER TABLE wallet_ledger_entries DROP CONSTRAINT ledger_payout_owner');
        DB::statement('ALTER TABLE commission_entries DROP CONSTRAINT commission_owner_unique');
        DB::statement('ALTER TABLE payout_requests DROP CONSTRAINT payout_owner_unique');
        DB::statement('DROP INDEX one_verified_payment_per_order');
        DB::statement('ALTER TABLE wallet_ledger_entries DROP CONSTRAINT wallet_ledger_entries_invariants');
        DB::statement('ALTER TABLE payout_requests DROP CONSTRAINT payout_requests_invariants');
        DB::statement('ALTER TABLE commission_entries DROP CONSTRAINT commission_entries_invariants');
        DB::statement('ALTER TABLE refunds DROP CONSTRAINT refunds_invariants');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_invariants');
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT order_items_invariants');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_invariants');
        DB::statement('ALTER TABLE commission_rules DROP CONSTRAINT commission_rules_invariants');
        DB::statement('ALTER TABLE cart_items DROP CONSTRAINT cart_items_invariants');
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT inventory_movements_invariants');
        DB::statement('ALTER TABLE inventory_balances DROP CONSTRAINT inventory_balances_invariants');
        DB::statement('ALTER TABLE partners DROP CONSTRAINT partners_invariants');
        DB::statement('ALTER TABLE product_variants DROP CONSTRAINT product_variants_invariants');
        DB::statement('ALTER TABLE categories DROP CONSTRAINT categories_invariants');
        DB::statement('ALTER TABLE products DROP CONSTRAINT products_invariants');
    }
};
