# Phase 3 ? Referral dan komisi direct

Tanggal: 27 Oktober 2026. Status: implementasi MVP selesai, menggunakan driver MySQL/InnoDB. Lingkungan pengujian lokal adalah MariaDB 10.4.32; bukan klaim pengujian server MySQL 8.4 remote.

## Hasil

Pengguna terverifikasi dapat mengajukan kemitraan dan sponsor opsional. Store Admin menyetujui, menolak atau menangguhkan mitra dengan audit/idempotency key. Sponsor yang sudah disetujui immutable. Tidak ada bonus pendaftaran/rekrutmen.

Referral memakai first-valid token hash, cookie HttpOnly/SameSite=Lax terenkripsi, TTL 30 hari, pemeriksaan mitra aktif dan larangan self referral. Snapshot per item menyimpan versi/rate/cap/budget/return window saat checkout. Prioritas aturan produk, kategori, lalu global. Produk noneligible atau tanpa aturan published tidak menghasilkan komisi.

Finance membuat draft, meninjau simulasi, lalu publish direct_v1 level 1 dengan acknowledgment. Tidak ada tarif contoh yang otomatis published. Engine membatasi budget snapshot, membulatkan IDR ke bawah dan mencatat entitlement unik. Release memerlukan delivery dan masa retur berakhir; fraud hold/suspended partner membatasi operasi. Order completed diproses scheduler dengan audit.

## File ditambah/diubah

- app/Domains/Partners/{Models/Partner.php, Models/ReferralAttribution.php, Actions/ManagePartner.php, Services/ReferralCapture.php}
- app/Domains/Commissions/{Models/CommissionRule.php, Models/CommissionEntry.php, Actions/ManageCommissionRule.php, Services/CheckoutAttribution.php, Services/CommissionEngine.php}
- app/Domains/Orders/{Actions/PlaceOrder.php, Actions/ManageOrder.php, Models/Order.php, Models/OrderItem.php}
- app/Domains/Catalog/Actions/SaveProduct.php
- app/Http/Controllers/{PartnerController.php, CheckoutController.php, Admin/PartnerManagementController.php, Admin/CommissionRuleController.php}
- app/Http/Requests/{Partner/PartnerApplicationRequest.php, Admin/CommissionRuleRequest.php, Admin/ProductRequest.php}
- app/Policies/PartnerPolicy.php, app/Providers/AuthorizationServiceProvider.php
- app/Console/Commands/ProcessFinance.php, config/partners.php, routes/web.php, routes/console.php
- database/migrations/2026_10_27_003400_add_partner_finance_workflow_controls.php (dipakai bersama Phase 4)
- database/factories/{PartnerFactory.php, ReferralAttributionFactory.php, CommissionRuleFactory.php, CommissionEntryFactory.php}
- database/seeders/FinanceSandboxSeeder.php (opsional lokal/testing; hanya draft nol, tidak dipublish)
- resources/js/pages/{partner/dashboard.tsx, admin/partners.tsx, admin/commission-rules.tsx, admin/catalog.tsx}
- tests/FinanceTestCase.php, tests/Feature/{PartnerReferralTest.php, DirectCommissionTest.php}
- Generated resources/js/actions, resources/js/routes dan public/build diperbarui oleh Wayfinder/build.

## Command dan verifikasi

```powershell
php artisan migrate --no-interaction
# Test hanya pada database khusus yang boleh dibersihkan:
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='honeycommerce_phase2_test'
php vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/PartnerReferralTest.php tests/Feature/DirectCommissionTest.php
php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
npm run types:check
npm run build
```

Subset Phase 3: 10 test. Full QA lintas fase: **218 tests / 677 assertions lulus tanpa skip**. Migration telah diterapkan ke database lokal aktif. Pengujian menemukan implicit ON UPDATE pada tanggal bisnis MariaDB; migration mengubah kolom terkait menjadi DATETIME agar publish/status update tidak mengganti tanggal efektif/placed_at.

## Risiko dan langkah berikutnya

Default development masa retur 7 hari, budget ceiling 3000 bps dan minimum payout Rp50.000 masih perlu persetujuan pemilik; ceiling bukan tarif komisi aktif. Tidak ada komisi multi-level atau perubahan sponsor retroaktif. Langkah implementasi berikutnya telah diselesaikan dalam Phase 4: ledger, payout, refund/reversal.

## Penyelesaian lanjutan ? 28 Oktober 2026

Audit lintas Phase 3?5 dan QA ulang selesai untuk MVP direct satu level. Tidak mengaktifkan multi-level atau menerbitkan tarif bisnis otomatis. CommissionEngine kini memakai entitlement asli sebagai dasar prorata refund, termasuk catch-up ketika refund terjadi sebelum accrual. Snapshot rule tidak diubah dan tidak dihitung ulang memakai tarif versi baru.

File tambahan yang berubah pada tahap ini: app/Domains/Commissions/Services/CommissionEngine.php, app/Domains/Shared/ValueObjects/Idr.php dan tests/FinanceTestCase.php. Idr::proportion menggunakan integer long multiplication untuk floor(amount ? part ? whole), tanpa floating point dan tanpa overflow intermediate. tests/Unit/IdrProportionTest.php mencakup pembulatan serta batas PHP_INT_MAX.

Full suite terkini: **235 tests / 797 assertions lulus**, tanpa skip, pada database test MySQL driver/MariaDB lokal. Detail kesiapan runtime ada pada laporan Phase 5 yang diperbarui.
