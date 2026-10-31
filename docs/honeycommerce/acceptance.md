# Acceptance specification - Phase 0 dan gate fase berikut

Dokumen ini memuat riwayat acceptance Phase 0?2 dan hasil MVP Phase 3?5. Implementasi terbaru menggunakan domain models/factories dan data sintetis di database test khusus. Catatan fase terdahulu tetap menunjukkan batas implementasi pada saat fase itu selesai.

## Acceptance Phase 0 yang executable

| Perilaku                                                                       | Bukti                                           |
| ------------------------------------------------------------------------------ | ----------------------------------------------- |
| Migrasi MySQL/InnoDB berhasil, down dapat dijalankan dan up ulang              | command migrate/rollback pada cluster sementara |
| Semua uang integer, overflow/negative/discount invalid ditolak                 | HoneyCommerceRulesTest                          |
| Komisi floor bps dari net merchandise, cap dan fixed tidak melebihi net        | HoneyCommerceRulesTest                          |
| Rule version invalid, expiry, multi-level/recruitment strategy ditolak         | HoneyCommerceRulesTest                          |
| Unpaid tidak dapat shipped; payment tidak double verify; payout perlu approved | state machine data provider                     |
| Produk nonaktif dan reservasi melebihi stok ditolak                            | pure domain service tests                       |
| Self-referral, suspended partner dan token expired tidak eligible              | ReferralEligibility test                        |
| Payout melebihi available/minimum, nol atau suspended ditolak                  | PayoutEligibility data provider                 |
| Saldo dihitung dari signed ledger, overflow ditolak                            | BalanceCalculator tests                         |
| Manual payment selalu pending dan nominal nol ditolak                          | ManualPaymentAdapter tests                      |
| Status + audit + outbox atomik; retry identik tidak double-write               | HoneyCommerceFoundationTest                     |
| Key replay berbeda target ditolak, invalid transition tidak punya side effects | HoneyCommerceFoundationTest                     |
| FK invalid actor menyebabkan perubahan status rollback                         | HoneyCommerceFoundationTest                     |
| Order number dan entitlement komisi/idempotency ledger unique                  | HoneyCommerceFoundationTest                     |
| MySQL/InnoDB CHECK stok/uang/state/direct-level berlaku                        | invalidDatabaseValues data provider             |
| Ledger/audit immutable; published rule/sponsor tidak dapat diganti             | MySQL/InnoDB trigger tests                      |
| Ledger cross-owner dan dua pembayaran verified ditolak                         | MySQL/InnoDB FK/generated unique-index tests    |

## Acceptance wajib sebelum tiap fase dianggap selesai

### Phase 1 - auth dan roles

Login/logout/register/reset/verified email/2FA starter kit tetap lulus. Spatie permissions terpasang pada stable compatible version. Super Admin, Admin Toko, Finance, Mitra, Customer memiliki permission granular. Tests Policies membuktikan ownership, suspended status, dan 403 tanpa permission; privilege escalation via request role ditolak. Perubahan role dicatat audit tanpa secrets. Config MySQL/InnoDB/Redis hanya diterapkan setelah izin perubahan config/dependency.

### Phase 2 - katalog, cart, checkout, payment manual

FR-01/02/03/04: catalog aktif dan stok akurat; cart hanya variant sah/aktif; harga server authoritative. Dua proses MySQL/InnoDB berebut stok terakhir: tepat satu sukses dan stock tidak negatif. Idempotent checkout dengan payload sama mengembalikan order sama; key sama payload berbeda ditolak. Diskon total teralokasi deterministik. Semua snapshot alamat/harga/referral/rule tersimpan. Duplicate payment verification tidak menggandakan stok/event. Proof private hanya dapat dibaca owner/Finance; unpaid tidak bisa dikirim. Cancel/expiry release stock tepat sekali.

### Phase 3 - referral dan komisi direct

FR-05/06: registration tidak menulis commission/ledger. First-valid attribution tidak dihijack referral berikutnya; self/suspended/expired ditolak. Published/effective rule + eligible product + payment valid wajib. Double OrderPaid job/webhook dan concurrent handler hanya membuat satu entitlement per item/beneficiary/rule. Rule/sponsor tidak retrospektif. Net discount, split item, cap budget dan zero commission diuji. Komisi available hanya setelah delivered + masa retur; refund partial memakai cumulative entitlement, full refund reverses, retry tidak double reversal. Approval rule di luar budget ditolak. Multi-level tetap ditolak.

### Phase 4 - wallet dan payout

FR-07/08: saldo merupakan aggregate ledger; customer/mitra lain tidak dapat membaca. Dua proses MySQL/InnoDB melakukan payout bersamaan dengan total di atas available: hanya request yang terdanai yang berhasil. Reserve/release/settle masing-masing idempotent dan balance legs cocok. Rejected/failed mengembalikan reserve sekali. Paid mensyaratkan Finance + approval + bukti + reference unik. Refund sesudah paid menghasilkan receivable/offset, bukan menghapus histori atau mentransfer uang lagi. Ambiguous transfer direkonsiliasi. Ledger dan laporan bisa direkonsiliasi.

### Phase 5 - QA dan deploy

Full suite + frontend type/build check + MySQL/InnoDB concurrency tests + E2E alur customer/admin/mitra. Reports date/filter/role-scope dan CSV aman; stok/order/komisi/ledger reconcile. Queue duplicate/retry/outbox recovery terbukti. Backup restore drill staging, secrets/permissions, private uploads dan observability diverifikasi. Production deployment, production data migration dan uang riil memerlukan approval eksplisit terpisah.

## Menjalankan test aman

PHPUnit memakai RefreshDatabase: hanya arahkan ke database test yang boleh dihapus. `.env` aktif tidak perlu diubah. Untuk SQLite smoke:

```powershell
php -d extension=pdo_sqlite vendor/bin/phpunit tests/Unit/HoneyCommerceRulesTest.php tests/Feature/HoneyCommerceFoundationTest.php
```

MySQL/InnoDB: siapkan database test kosong sendiri, ekspor variabel proses DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD dan DB_URL kosong. Jangan menaruh password ke dokumen/log.

```powershell
php vendor/bin/phpunit -c phpunit.mysql.xml tests/Unit/HoneyCommerceRulesTest.php tests/Feature/HoneyCommerceFoundationTest.php
```

SQLite sengaja skip CHECK/trigger MySQL/InnoDB. Hasil MySQL/InnoDB menjadi bukti target aktif mulai Phase 2; hasil fase sebelumnya tetap historis. Concurrency checkout/commission/payout tests baru bermakna setelah actions terkait diimplementasikan; schema test bukan klaim concurrency workflow telah selesai.

## Hasil implementasi Phase 2

CommerceCheckoutTest, CommerceOperationsTest, CatalogManagementTest dan CommerceConcurrencyTest membuktikan flow checkout sampai delivered, stok, idempotency, private proof, permission boundary, expiry dan dua proses perebut stok terakhir. Snapshot referral/rule saat ini null (fitur atribusi Phase 3); komisi tidak diposting. Diskon per-unit dikalikan quantity tanpa float atau sisa pembulatan. Refund/return, komisi dan payout tetap acceptance fase selanjutnya, belum dianggap selesai.

Gunakan phpunit.mysql.xml dengan database khusus berakhiran _test. TestCase menolak database MySQL tanpa suffix itu sebelum lifecycle migration test berjalan. Contoh: ekspor DB_CONNECTION=mysql dan DB_DATABASE=honeycommerce_phase2_test di shell, lalu jalankan php vendor/bin/phpunit -c phpunit.mysql.xml. Jangan arahkan ke db_mlm atau database operasional. phpunit.xml default SQLite hanya smoke test dan melewati invariant/concurrency engine nyata.

## Hasil implementasi MVP Phase 3?5

| Acceptance                                                                    | Bukti executable                          |
| ----------------------------------------------------------------------------- | ----------------------------------------- |
| Pengajuan/approval tidak menghasilkan komisi rekrutmen                        | PartnerReferralTest                       |
| First-valid referral, expiry, partner suspended dan self referral             | PartnerReferralTest, DirectCommissionTest |
| Produk eligible, paid, rule published/effective, snapshot tanpa repricing     | DirectCommissionTest                      |
| Maturity sesudah delivery/return window; fraud hold                           | DirectCommissionTest, MvpJourneyTest      |
| Reserve FIFO, overdraw, retry identik, review pemilik dilarang                | WalletPayoutTest, FinanceConcurrencyTest  |
| Manual paid memerlukan bukti, referensi dan konfirmasi                        | WalletPayoutTest                          |
| Refund pending/available/reserved/paid, rounding dan debt offset              | RefundReversalTest                        |
| Akses wallet/payout mitra lain, laporan finansial dan pelanggan               | ReportsSecurityTest                       |
| CSV formula escaping, informasi publik dan halaman admin                      | ReportsSecurityTest                       |
| Outbox retry tidak menggandakan efek domain/notifikasi yang berhasil diproses | MvpJourneyTest                            |
| Rekonsiliasi saldo serta kesalahan allocation                                 | WalletPayoutTest, RefundReversalTest      |
| Dump/restore SQL dan trigger ledger immutable pulih                           | MySqlBackupRestoreTest                    |

FinanceConcurrencyTest menggunakan dua proses PHP dengan barrier dan koneksi InnoDB sungguhan. MySqlBackupRestoreTest memakai mysqldump/mysql client, membuat database restore acak berakhiran _test, membandingkan isi tujuh tabel, lalu menguji trigger dan membersihkan artefak. Uji ini dilaksanakan lokal; tidak mengklaim restore di staging/produksi.

UAT bisnis, konten legal final, konfigurasi tarif nyata, browser E2E lintas perangkat, load test P95, monitoring produksi dan deployment tetap memerlukan environment serta persetujuan pemilik. Fitur multi-level, gateway, WhatsApp, multi-gudang dan rank bukan scope MVP aktif.
