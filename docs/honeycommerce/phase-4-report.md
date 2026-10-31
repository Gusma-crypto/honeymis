# Phase 4 ? Wallet, payout dan refund

Tanggal: 27 Oktober 2026. Status: implementasi teknis MVP selesai; tidak ada uang yang dikirim.

## Hasil

Wallet append-only memiliki bucket pending, available, reserved, paid dan receivable. Semua writer mengunci partner dalam transaksi; request payout mereservasi nominal dan mengalokasikan FIFO per entitlement. IDR berupa integer. Unique idempotency key dan fingerprint menolak replay dengan payload berbeda. FK komposit memastikan allocation/ledger terkait pemilik yang benar.

Finance dapat approve/reject/fail dan mencatat paid setelah transfer manual dikonfirmasi, referensi unik serta bukti privat. Pengaju dilarang meninjau payout sendiri, termasuk ketika memiliki role Finance. Rekening dienkripsi, tampilan mitra hanya last4; bank tidak ada dalam audit/CSV.

Customer dapat mengajukan refund per item atau ongkir/pajak. Finance memproses atau menolak. Refund parsial memakai entitlement kumulatif agar pembulatan tidak berlipat; refund ongkir/pajak tidak memicu reversal komisi. Payout terkait yang belum paid dibatalkan dan reservasi dilepas. Reversal setelah paid menghasilkan receivable; komisi berikutnya melunasi piutang sebelum dapat ditarik. Full refund paid/processing melepaskan reservasi stok. Barang yang sudah dikirim memerlukan inspeksi dan penyesuaian stok manual oleh Inventory.

Rekonsiliasi read-only mengecek `pending + available + reserved + paid - receivable = komisi net`, saldo nonnegatif, payout reserve/paid, sum allocation dan snapshot totals order. Shared partner lock menjaga pembacaan konsisten saat writer berjalan.

## File ditambah/diubah

- app/Domains/Wallet/{Models/WalletLedgerEntry.php, Services/PartnerLedger.php}
- app/Domains/Payouts/{Models/PayoutRequest.php, Actions/ManagePayout.php}
- app/Domains/Orders/{Models/Refund.php, Actions/ProcessRefund.php, Enums/OrderStatus.php}
- app/Http/Controllers/{WalletController.php, PayoutController.php, OrderController.php, Admin/PayoutManagementController.php, Admin/RefundController.php}
- app/Http/Requests/{Partner/PayoutRequest.php, Admin/PayoutDecisionRequest.php, Admin/RefundRequest.php}
- app/Policies/PayoutPolicy.php, bootstrap/app.php (dontFlash rekening), app/Providers/AuthorizationServiceProvider.php
- app/Console/Commands/ReconcileFinance.php
- resources/js/pages/{partner/wallet.tsx, partner/payouts.tsx, admin/payouts.tsx, admin/refunds.tsx, commerce/order.tsx}
- resources/js/components/order-finance-controls.tsx
- database/factories/{WalletLedgerEntryFactory.php, PayoutRequestFactory.php, RefundFactory.php}
- Migration dan route bersama Phase 3; generated TypeScript routes/actions serta public/build.
- tests/Feature/{WalletPayoutTest.php, RefundReversalTest.php, FinanceConcurrencyTest.php}, tests/FinanceTestCase.php

## Command dan hasil test

```powershell
# Hanya database test berakhiran _test:
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='honeycommerce_phase2_test'
php vendor/bin/phpunit -c phpunit.mysql.xml tests/Feature/WalletPayoutTest.php tests/Feature/RefundReversalTest.php tests/Feature/FinanceConcurrencyTest.php
php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```

Subset Phase 4: 11 test. Dua proses nyata mengajukan masing-masing Rp8.000 atas saldo Rp10.000: tepat satu diterima, satu ditolak; reserved Rp8.000 dan available Rp2.000. Refund setelah payout paid menimbulkan piutang dan penghasilan berikutnya melunasinya. Rekonsiliasi juga mendeteksi allocation yang tidak seimbang. Full suite **218 tests / 677 assertions lulus tanpa skip** pada MySQL driver/MariaDB lokal.

## Risiko dan langkah berikutnya

Status paid/refund adalah pencatatan pernyataan Finance bahwa transfer manual sudah dilakukan; aplikasi tidak mengakses atau memverifikasi saldo bank. Transfer dengan hasil tidak pasti perlu rekonsiliasi di bank, bukan pengiriman ulang. Bukti privat dan APP_KEY wajib ikut prosedur backup. Audit/ledger tidak diedit untuk memperbaiki selisih; gunakan transaksi koreksi yang ditinjau. QA dan persiapan deployment dijelaskan pada Phase 5.

## Penyelesaian lanjutan ? 28 Oktober 2026

Diperbaiki: refund parsial pada komisi nominal tetap atau komisi yang menyentuh cap sebelumnya dapat mempertahankan nominal komisi terlalu besar. Sisa entitlement sekarang dihitung sebagai floor(komisi asli ? sisa net merchandise ? net merchandise asli); reversal kumulatif adalah komisi asli dikurangi sisa tersebut. Ini mempertahankan pembulatan kumulatif dan berlaku pada pending/available/reserved/paid melalui writer reversal yang sama. Refund ongkir/pajak tetap tidak memotong komisi produk.

File berubah: app/Domains/Orders/Actions/ProcessRefund.php, app/Domains/Commissions/Services/CommissionEngine.php, app/Domains/Shared/ValueObjects/Idr.php, tests/Feature/RefundReversalTest.php, tests/Unit/IdrProportionTest.php dan fixture tests/FinanceTestCase.php. Regression tests baru membuktikan refund 50% dari fixed/capped commission Rp10.000 menyisakan Rp5.000, serta rekonsiliasi lulus. Tidak ada penyesuaian uang pada data operasional yang dilakukan untuk menerapkan kode ini.

Full suite **235 tests / 797 assertions lulus tanpa skip**. Rekonsiliasi read-only database lokal aktif: **0 discrepancies**. Tidak mengirim transfer bank.
