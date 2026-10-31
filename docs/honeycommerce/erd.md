# ERD - HoneyCommerce Phase 0

Schema sumber: empat migration `*honey_commerce*` pada `database/migrations`. Semua tabel memakai bigint primary key. Auth users/tables sudah ada dan tidak dibuat ulang. Migrations Spatie disiapkan oleh vendor pada Phase 1, belum termasuk 24 tabel domain ini.

```mermaid
erDiagram
    users ||--o| customers : profile
    users ||--o| partners : partner_account
    customers ||--o{ addresses : addresses
    customers o|--o{ carts : owns
    customers ||--o{ orders : places
    categories o|--o{ categories : parent
    categories ||--o{ products : categorizes
    products ||--o{ product_variants : variants
    products ||--o{ product_images : images
    product_variants ||--o| inventory_balances : lockable_projection
    product_variants ||--o{ inventory_movements : history
    carts ||--o{ cart_items : contains
    product_variants ||--o{ cart_items : selected
    partners o|--o{ partners : immutable_sponsor
    partners ||--o{ referral_attributions : attribution
    customers o|--o{ referral_attributions : attributed_customer
    partners o|--o{ orders : direct_beneficiary
    products o|--o{ commission_rules : optional_product_scope
    categories o|--o{ commission_rules : optional_category_scope
    users o|--o{ commission_rules : approved_by
    commission_rules o|--o{ orders : snapshotted_rule
    orders ||--|{ order_items : contains
    product_variants ||--o{ order_items : snapshot_source
    orders ||--o{ payments : payment_attempts
    users o|--o{ payments : verified_by
    orders ||--o{ shipments : fulfillment
    orders ||--o{ refunds : refund_requests
    order_items o|--o{ refunds : optional_item_scope
    users o|--o{ refunds : processed_by
    order_items ||--o{ commission_entries : earns
    partners ||--o{ commission_entries : beneficiary
    commission_rules ||--o{ commission_entries : rule_version
    partners ||--o{ payout_requests : requests
    users o|--o{ payout_requests : approved_by
    partners ||--o{ wallet_ledger_entries : ledger_owner
    commission_entries o|--o{ wallet_ledger_entries : accrual_adjustment
    payout_requests o|--o{ wallet_ledger_entries : reserve_settle
    refunds o|--o{ wallet_ledger_entries : refund_adjustment
    wallet_ledger_entries o|--o{ wallet_ledger_entries : reversal_of
    users o|--o{ audit_logs : actor

    orders {
        bigint id PK
        string order_number UK
        string idempotency_key UK
        bigint customer_id FK
        bigint partner_referral_id FK
        bigint commission_rule_id FK
        json referral_snapshot
        json rule_snapshot
        json shipping_address_snapshot
        bigint subtotal
        bigint discount_total
        bigint shipping_total
        bigint tax_total
        bigint grand_total
        string currency
        string status
        timestamp placed_at
        timestamp delivered_at
    }
    commission_rules {
        bigint id PK
        integer version UK
        string strategy
        smallint level
        bigint product_id FK
        bigint category_id FK
        integer rate_bps
        bigint fixed_amount
        bigint cap_amount
        timestamp valid_from
        timestamp valid_until
        string status
    }
    commission_entries {
        bigint id PK
        bigint order_item_id FK
        bigint beneficiary_partner_id FK
        bigint rule_id FK
        smallint level
        bigint base_amount
        bigint amount
        string unique_key UK
        string status
        timestamp eligible_at
    }
    payout_requests {
        bigint id PK
        bigint partner_id FK
        string idempotency_key UK
        bigint amount
        text bank_account_encrypted
        string transfer_reference UK
        string proof_storage_key
        string status
        bigint approved_by FK
        timestamp paid_at
    }
    wallet_ledger_entries {
        bigint id PK
        bigint partner_id FK
        bigint commission_entry_id FK
        bigint payout_id FK
        bigint refund_id FK
        bigint reversal_of_id FK
        string transaction_key
        string idempotency_key UK
        string bucket
        string entry_type
        bigint amount_signed
        string currency
        timestamp created_at
    }
    inventory_balances {
        bigint id PK
        bigint variant_id FK,UK
        integer on_hand
        integer reserved
    }
    outbox_messages {
        bigint id PK
        string event_key UK
        string event_type
        string aggregate_type
        bigint aggregate_id
        json payload
        timestamp available_at
        timestamp processed_at
        integer attempts
    }
    webhook_events {
        bigint id PK
        string provider
        string event_id
        boolean signature_verified
        string payload_hash
        timestamp processed_at
    }
```

Cardinality diagram menunjukkan kontrak bisnis; minimal satu order_item ditegakkan checkout action, bukan FK saja. Audit/outbox/inventory polymorphic references memakai subject/aggregate/reference type + id dan tidak bisa diberi FK ke satu tabel; source entity disimpan immutable/restrict sesuai domain dan divalidasi action.

## Constraint penting

- Unique: product/variant sku, category/product slug, referral code, user partner/customer, token_hash, cart+variant, order number/idempotency, rule version, commission entitlement tuple, ledger/payout/refund/payment idempotency, provider+event_id, provider+payment reference.
- Semua parent historis memakai FK restrict-on-delete. Produk yang pernah dibeli dinonaktifkan, bukan dihapus.
- MySQL generated verified_order_id dengan unique index: hanya satu payment verified/refunded per order. Multiple pending/rejected attempts diperbolehkan.
- FK komposit ledger memastikan partner_id cocok dengan beneficiary komisi/pemilik payout, bukan sekadar parent ID ada.
- CHECK: level selalu 1, direct_v1, rule version positif, rate 0-10000 bps, nominal positif/nonnegatif sesuai domain, total order konsisten, stock reserved <= on_hand, status dari allowlist, currency IDR, metadata approval/proof ketika paid.
- Trigger: wallet ledger dan audit append-only; rule published immutable (status boleh retired); sponsor immutable sesudah approved_at.
- Clock timestamp; batas rule/report menggunakan interval `[from, until)`. Data sensitif JSON bukan tempat menyimpan rekening plaintext.
- Redis tidak menyimpan saldo utama. `inventory_balances` hanya projection yang diperbarui bersama movement dalam satu transaksi.

## Batas enforcement Phase 0

Konsistensi jumlah order_items vs subtotal, nominal payment vs order, refund kumulatif, eligibility paid/delivered, sponsor cycle antarbaris, ledger double-entry dan minimum payout tetap memerlukan action domain transaksional. Belum ada endpoint domain yang menulisnya. CHECK schema bukan pengganti Policies/validation/business actions. Workflow dan acceptance gate fase terkait dirinci pada [acceptance.md](acceptance.md).

## Penambahan Phase 2 - MySQL

- product_variants.discount_amount BIGINT: diskon rupiah per unit, 0 sampai price.
- orders.request_fingerprint CHAR/VARCHAR(64), cart_id FK nullable, expires_at nullable dan index status+expires_at.
- payments.proof_hash untuk fingerprint bukti; verified_order_id generated nullable dan unique, terisi order_id hanya untuk verified/refunded.
- JSON di MySQL/MariaDB dan timestamp dikelola dalam timezone aplikasi; jangan mengandalkan tipe PostgreSQL JSONB/timestamptz.
- Self sponsor/parent dicegah lewat trigger; audit/ledger append-only, rule/sponsor immutable dan owner composite FK dipasang migration MySQL.
- Model/factory domain commerce hadir pada app/Domains dan database/factories. Tidak ada migrasi data ke PostgreSQL.

## Penambahan workflow finance MVP

```mermaid
erDiagram
    PARTNERS ||--o{ PAYOUT_ALLOCATIONS : owns
    PAYOUT_REQUESTS ||--o{ PAYOUT_ALLOCATIONS : reserves
    COMMISSION_ENTRIES ||--o{ PAYOUT_ALLOCATIONS : funds
    PAYOUT_ALLOCATIONS {
        bigint id PK
        bigint payout_id FK
        bigint commission_entry_id FK
        bigint partner_id FK
        bigint amount
    }
```

payout_allocations UNIQUE(payout_id, commission_entry_id); CHECK amount > 0; composite owner FK (payout_id, partner_id) dan (commission_entry_id, partner_id). orders menambah fraud_hold, commission_checked_at dan return_window_days. order_items menyimpan commission_rule_id dan commission_snapshot. commission_entries menyimpan reversed_amount/offset_amount; payout_requests menyimpan request_fingerprint dan bank_last4; refunds menyimpan request_fingerprint. Rekening payout tetap encrypted:array, tidak disalin ke ledger/audit/CSV.
