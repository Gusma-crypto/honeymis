# Phase 5 ? QA dan persiapan deployment

Tanggal: 27 Oktober 2026. Status: QA lokal dan persiapan deployment selesai. Produksi belum dideploy; UAT bisnis belum ditandatangani.

## Hasil dan file ditambah/diubah

- app/Domains/Reports/Services/CommerceReport.php: ringkasan penjualan/refund/komisi/margin, batas role dan CSV terproteksi.
- app/Domains/Shared/Services/{FinanceOutbox.php, CommerceNotifier.php}, app/Notifications/CommerceUpdate.php: durable outbox dan queued email setelah commit untuk order, status mitra, payout dan refund.
- app/Http/Controllers/{DashboardController.php, StoreInfoController.php, Admin/ReportController.php, Admin/AuditController.php, Admin/CustomerController.php}.
- resources/js/pages/{dashboard.tsx, shop/info.tsx, shop/catalog.tsx, shop/product.tsx, admin/reports.tsx, admin/audit.tsx, admin/customers.tsx, admin/customer.tsx, admin/commerce-settings.tsx}.
- resources/js/components/{app-sidebar.tsx, commerce-ui.tsx}: navigasi berdasarkan permission serta informasi publik/footer.
- config/commerce.php, .env.example, composer.json (scheduler pada composer run dev), .github/workflows/tests.yml (PHP 8.4, MySQL test service, lint/TS/static/test).
- tests/Feature/{ReportsSecurityTest.php, MvpJourneyTest.php, MySqlBackupRestoreTest.php}; fixture bersama tests/FinanceTestCase.php. Subset QA ini 8 test.
- docs/honeycommerce/{architecture.md, adrs.md, erd.md, state-machines.md, acceptance.md, phase-3-report.md, phase-4-report.md, phase-5-report.md, deployment-runbook.md}.
- Generated resources/js/actions, resources/js/routes dan public/build diperbarui. Tujuh model stub kosong yang dihasilkan selama pengerjaan di app/Models/Domains dibersihkan; model nyata berada di app/Domains. Stub lama fase sebelumnya tidak diubah.
- Formatter npm run check:fix juga merapikan Markdown referensi PRD dan laporan Phase 0/state-machines yang sebelumnya gagal format; tidak mengubah keputusan/scope PRD. Pint memformat file PHP yang berubah; generated bootstrap cache dapat diregenerasi oleh Laravel.

## Command dan hasil

```powershell
php artisan migrate --no-interaction
# Migration finance berhasil pada database lokal aktif.
# Semua perintah PHPUnit berikut hanya pada database test:
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='honeycommerce_phase2_test'
php vendor/bin/phpunit -c phpunit.mysql.xml
php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
npm run check
npm run types:check
npm run build
php vendor/bin/pint --dirty --format agent
# Workspace tidak memiliki .git; --dirty ditolak oleh Pint.
# Fallback yang dijalankan:
php vendor/bin/pint app database routes tests config bootstrap --format agent
```

| Pemeriksaan                                            | Hasil                                                          |
| ------------------------------------------------------ | -------------------------------------------------------------- |
| Full PHPUnit, driver mysql pada MariaDB lokal          | **218 test, 677 assertion, lulus tanpa skip**                  |
| Payout dua proses + checkout stok terakhir             | Lulus; satu request berhasil dan satu ditolak                  |
| Refund pending/available/reserved/paid dan debt offset | Lulus, rekonsiliasi konsisten                                  |
| Backup/restore lokal lewat mysql CLI                   | Isi tujuh tabel sama; trigger ledger immutable kembali bekerja |
| PHPStan level 7                                        | 0 error                                                        |
| TypeScript                                             | Lulus                                                          |
| Format/lint global                                     | Lulus tanpa warning/lint error                                 |
| Vite build                                             | Lulus; warning optional fontaine, tidak menambah dependency    |
| Migration lokal                                        | Finance workflow controls berhasil diterapkan                  |
| CI remote, browser E2E, staging/produksi               | Belum dijalankan; tidak diklaim lulus                          |

Setelah pemisahan fixture test agar setiap skenario membuat actor secara eksplisit, subset 29 test finance dijalankan ulang: **29 tests / 149 assertions lulus tanpa skip**. Tidak ada perubahan perilaku domain sesudah full suite tersebut.

## Risiko dan langkah berikutnya

MVP teknis berfungsi untuk katalog ? checkout ? pembayaran manual ? pemenuhan ? direct commission ? wallet ? payout manual, dengan refund/reversal, role/policy, audit, laporan dan notifikasi antrean. Local queue/cache/session tetap database; service Redis dan SMTP nyata belum diprovisikan. Tarik saldo/record paid tetap memerlukan tindakan Finance. Tidak ada rate published atau admin baru yang dipilih otomatis.

Langkah berikutnya adalah UAT pemilik dan pengisian konfigurasi bisnis/legal pada deployment-runbook.md. Deployment/transfer/perubahan data produksi tidak dijalankan tanpa persetujuan eksplisit. PRD fitur fase lanjut (multi-level/gateway/WhatsApp/scale) tetap di luar MVP aktif.

## Penyelesaian dan mode produksi lokal ? 28 Oktober 2026

Pengguna memilih komputer lokal, tanpa publikasi internet. Runtime sekarang APP_ENV=production, APP_DEBUG=false, COMMERCE_USE_BUILT_ASSETS=true, SSR/Boost nonaktif, session encrypted, serta config/events/routes/views cached. APP_URL kanonis menjadi http://127.0.0.1:8000 untuk menyesuaikan binding IPv4 dan menghindari jeda resolusi localhost yang terukur di mesin ini. Cookie secure=false hanya karena target loopback HTTP; tidak menjadi konfigurasi deployment publik. APP_KEY dan seluruh konfigurasi koneksi database dipertahankan. Queue/cache/session tetap database; mail log tetap lokal.

Outbox sekarang mengantrekan job notifikasi di dalam transaksi consumer sebelum event di-acknowledge. Pada driver database yang sama, INSERT job dan processed_at atomik; kegagalan enqueue tidak menghilangkan event. Kegagalan diberi attempt counter dan exponential backoff 5?300 detik; event sehat berikutnya tetap diproses, sedangkan command melaporkan kegagalan agar operator dapat meninjaunya. Perubahan status pembayaran juga diproses sebagai notifikasi. Event state komisi/payout yang dikenal diakui sebagai event domain; tipe asing tidak diabaikan diam-diam. Transport antrean eksternal tetap at-least-once dan dapat mengirim email ulang jika hasil enqueue/commit tidak pasti.

File ditambah/diubah:

- app/Domains/Shared/Services/FinanceOutbox.php; tests/Feature/FinanceOutboxTest.php (persist job sebelum ack, simulasi outage/retry, isolasi event rusak).
- app/Domains/Shared/ValueObjects/Idr.php; app/Domains/Commissions/Services/CommissionEngine.php; app/Domains/Orders/Actions/ProcessRefund.php; tests/Unit/IdrProportionTest.php; tests/Feature/RefundReversalTest.php; tests/FinanceTestCase.php.
- app/Console/Commands/CheckProductionReadiness.php: pemeriksaan runtime read-only, exit nonzero saat prasyarat belum terpenuhi.
- composer.json: production:prepare dan production:local; dev/dev:hot/production:local memanggil executable concurrently yang sudah terpasang, menghindari npx yang tertahan saat resolusi paket.
- .env: hanya flag runtime, URL loopback dan enkripsi session; bukan penggantian key/database. Generated bootstrap/cache dan public/build disiapkan ulang.
- docs/honeycommerce/phase-3-report.md, phase-4-report.md, phase-5-report.md dan deployment-runbook.md diperbarui.

Database lokal dicadangkan sebelum pergantian mode ke storage/app/private/pre-production-local-20261028-071639.sql. File berada di luar public web root. Backup restore acceptance test tetap memakai database test acak, bukan menimpa database operasional.

| Verifikasi akhir                               | Hasil                                            |
| ---------------------------------------------- | ------------------------------------------------ |
| Full suite MySQL sebelum config cache produksi | **235 tests / 797 assertions**, lulus tanpa skip |
| PHPStan                                        | 0 error                                          |
| TypeScript, lint/format, Vite build            | Lulus                                            |
| Composer validate                              | Lulus                                            |
| honey:check-production --local                 | Semua pemeriksaan OK                             |
| honey:reconcile-finance                        | 0 discrepancies                                  |
| HTTP root/login/health                         | 200                                              |
| Dashboard tanpa autentikasi                    | 302 ke login                                     |
| Endpoint Boost dan route tak dikenal           | 404, tanpa stack trace                           |
| Aset browser                                   | Hasil build, tanpa Vite client                   |
| Startup web/queue/scheduler                    | Diuji pada loopback, tidak dipublikasikan        |

Command setelah kode/asset berubah: php C:/composer/composer.phar run production:prepare. Startup: php C:/composer/composer.phar run production:local. Pemeriksaan tanpa menulis transaksi: php artisan honey:check-production --local dan php artisan honey:reconcile-finance.

Mode ini siap untuk uji/pemakaian lokal MVP dengan data bisnis yang diisi pemilik. Server publik, SMTP nyata, HTTPS, backup off-host, monitoring eksternal, UAT pemilik dan sign-off tarif/legal tetap belum diklaim selesai. Tidak ada fitur jaringan multi-level atau gateway yang diaktifkan.
