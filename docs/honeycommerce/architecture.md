# HoneyCommerce - Phase 0 architecture

Tanggal: 26 Oktober 2026. Referensi: `PRD_HoneyCommerce_Laravel.md` v1.0.
Status: MVP Phase 2?4 diimplementasikan; Phase 5 QA lokal dan persiapan deployment selesai. Deployment produksi dan UAT bisnis belum dilakukan. Keputusan pengguna terbaru: gunakan MySQL/InnoDB, bukan PostgreSQL. Catatan audit Phase 0 di bawah merupakan riwayat kondisi awal.

## Audit workspace

- Aplikasi Laravel sudah ada, bukan proyek kosong; tidak ditemukan `.git` atau `.ai/rules`.
- PHP CLI 8.4.27; Laravel 13.38.0; Inertia Laravel 3.5.2; Fortify 1.41.0; PHPUnit 12.5.40. Versi diperiksa melalui `composer show --direct` dengan `C:/composer/composer.phar`.
- React 19, TypeScript 5, Inertia React 3, Tailwind 4 berdasarkan manifest yang ada. Tidak mengganti bundler, UI starter kit, auth, atau route.
- Database aktif MySQL; tabel yang ada hanya users/auth/session/cache/queue/passkeys. Inspeksi melalui Boost `database-schema` (read-only).
- Cache/queue/session aktif berbasis database. PostgreSQL 17 tersedia lokal; PHP memiliki DLL pdo_pgsql dan pdo_sqlite yang belum aktif secara default. Redis PHP extension/server belum diverifikasi tersedia.
- Spatie Permission belum terpasang. Composer executable tidak ada di PATH, tetapi `C:/composer/composer.phar` tersedia.
- `.env`, `.env.example`, konfigurasi yang ada, dependencies, PRD, dan UI tidak diubah. Perbaikan timeout SSR sebelumnya dipertahankan.

Laravel 13 yang terpasang kompatibel dengan PHP 8.4 dan masih didukung menurut [release notes resmi](https://laravel.com/framework/docs/13.x/releases). Pertahankan versi terkunci; tidak perlu downgrade atau upgrade untuk Phase 0. Metadata [Spatie Permission](https://github.com/spatie/laravel-permission/blob/main/composer.json) menunjukkan dukungan Illuminate 13, tetapi versi stabil yang akan dipasang harus dipilih dan diperiksa oleh Composer pada Phase 1; jangan memasang branch development.

## Batas modul

| Domain      | Kepemilikan                                                                                | Fondasi saat ini                                                          |
| ----------- | ------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------- |
| Catalog     | categories, products, product_variants, product_images                                     | Purchasability                                                            |
| Orders      | customers, addresses, carts, cart_items, orders, order_items, payments, shipments, refunds | OrderTotals, state order/payment, PaymentAdapter + manual adapter         |
| Inventory   | inventory_balances, inventory_movements                                                    | StockAvailability                                                         |
| Partners    | partners, referral_attributions                                                            | ReferralEligibility                                                       |
| Commissions | commission_rules, commission_entries                                                       | RuleVersion, CommissionRule, DirectCommissionCalculator, state commission |
| Wallet      | wallet_ledger_entries                                                                      | BalanceCalculator                                                         |
| Payouts     | payout_requests                                                                            | PayoutEligibility, state payout                                           |
| Reports     | read models dari transaksi dan ledger                                                      | DateRange; tidak menyimpan saldo/omzet duplikat                           |
| Shared      | audit_logs, outbox_messages, webhook_events                                                | Idr, TransitionState, TransitionRecorder                                  |

Satu deployment Laravel, satu database MySQL/InnoDB; bukan microservices. Modul memanggil action/contract pemilik domain. Controller tetap tipis dalam struktur Laravel yang ada. Tidak ada controller, endpoint, listener finansial atau scheduler baru di Phase 0.

Struktur aktual:

```text
app/Domains/
  Catalog/Services/
  Orders/{Contracts,Enums,Services}/
  Inventory/Services/
  Partners/Services/
  Commissions/{Contracts,Enums,Services,ValueObjects}/
  Wallet/Services/
  Payouts/{Enums,Services}/
  Reports/Services/
  Shared/{Contracts,Services,ValueObjects}/
database/migrations/        # additive, empat migration HoneyCommerce
tests/Feature/HoneyCommerceFoundationTest.php
tests/Unit/HoneyCommerceRulesTest.php
docs/honeycommerce/
```

Pada fase implementasi tiap modul, tambahkan Models, Actions, Policies dan Events hanya saat dibutuhkan. Gunakan `app/Http/Requests` dan controller sesuai konvensi starter kit, serta `resources/js/pages` untuk Inertia. Register mapping policies domain secara eksplisit melalui provider ketika modelnya dibuat. Reports harus melakukan scope akses sebelum agregasi; CSV tidak boleh menampilkan rekening atau PII di luar izin.

## Database dan transaksi

Lihat [ERD](erd.md) dan [ADRs](adrs.md). Empat migration baru terdiri dari 24 tabel domain, FK dengan restrict-on-delete, unique constraints, indexes, CHECK dan trigger MySQL/MariaDB, generated-column unique payment, serta FK owner ledger. Migration PostgreSQL lama disimpan sebagai riwayat dan dilewati oleh driver MySQL.

`inventory_balances` adalah projection stok yang bisa dikunci, bukan sumber histori. Update on_hand/reserved dan insert movement harus dalam transaksi yang sama. Checkout mengunci variant/balance dengan urutan ID konsisten, memvalidasi harga/stok server-side, menyimpan snapshot, dan memakai unique idempotency key. Reservasi expired/cancelled dilepas dengan key tersendiri; pengiriman mengurangi on_hand dan reserved. Saldo wallet tidak ada sebagai kolom yang bisa diedit.

`TransitionRecorder` adalah primitive internal: lock aggregate, validasi graph, perubahan status + audit + outbox dalam satu DB transaction dengan deadlock retry. Replay key yang identik mengembalikan sukses tanpa entri kedua; key yang dipakai untuk target/actor lain ditolak. Ia tidak memverifikasi pembayaran, eligibility komisi atau saldo payout. Action domain wajib melakukan authorization dan business guard dalam transaksi luar yang sama. Jangan panggil langsung dari controller dan jangan menganggap state machine sebagai otorisasi.

Outbox menutup celah commit DB berhasil tetapi queue gagal. Worker pada fase terkait akan mengambil event terkomit dengan lock/claim, melakukan handling idempotent, dan menandai processed_at. Delivery bersifat at-least-once, bukan exactly-once. Redis mendukung queue/cache; database unique keys tetap menjadi penjaga kebenaran finansial.

## Uang dan ledger

- IDR rupiah utuh menggunakan BIGINT dan PHP int 64-bit. `Idr` memvalidasi nonnegatif dan overflow. Ledger bertanda memakai integer signed.
- Persentase adalah basis points: 1000 bps = 10%. Tidak ada float/decimal untuk nominal maupun rate.
- Commission floor pada rupiah utuh, cap per entitlement/item, tidak melebihi net merchandise. Ongkir/pajak dikecualikan.
- `discount_snapshot` adalah total diskon satu baris item, bukan diskon per unit. Diskon order harus dialokasikan deterministik ke item; pembulatan sisa diberikan menurut urutan order_item ID.
- Rule version immutable setelah publish. Snapshot disimpan pada checkout, evaluasi effective window berdasarkan placed_at, bukan waktu worker berjalan. Rule baru tidak mengubah order lama.
- Ledger bucket: pending, available, reserved, paid, receivable. Perpindahan bucket harus dua entri dengan transaction_key sama dan jumlah signed nol; accrual/reversal adalah perubahan kewajiban yang direkonsiliasi ke commission/refund.
- Pending commission: +pending. Release: -pending/+available. Reserve payout: -available/+reserved. Paid: -reserved/+paid. Reject/fail: -reserved/+available. Refund memakai entri reversal baru; tidak menghapus histori. Bucket paid adalah total payout historis, bukan saldo yang dapat ditarik.
- Refund setelah payout membuat receivable dan finance review. Availability untuk payout harus memperhitungkan outstanding receivable; jangan membayar saldo sebelum offset diselesaikan.
- Permintaan payout mengunci row partner sebagai mutex sebelum menghitung ledger dan reservasi. Dua request berbarengan tidak boleh melebihi available. Semua writer wallet harus mengikuti lock yang sama.

## Authorization dan validation yang harus dipasang sebelum endpoint aktif

Phase 1 memasang Spatie Permission dan Laravel Policies. Guard `web`; tidak ada bypass berbasis nama role dalam controller. User suspended ditolak pada action sensitif. Permission matrix awal:

| Resource/action          | Customer      | Mitra aktif                | Admin Toko  | Finance              | Super Admin           |
| ------------------------ | ------------- | -------------------------- | ----------- | -------------------- | --------------------- |
| order view               | milik sendiri | agregat atribusi tanpa PII | operasional | rekonsiliasi         | semua sesuai policy   |
| catalog/inventory write  | tidak         | tidak                      | ya          | tidak                | ya                    |
| payment verify/refund    | tidak         | tidak                      | tidak       | ya                   | ya                    |
| commission rule publish  | tidak         | tidak                      | tidak       | ya, dengan approval  | ya                    |
| wallet/payout view       | tidak         | milik sendiri              | tidak       | ya                   | ya                    |
| payout request           | tidak         | milik sendiri              | tidak       | tidak                | tidak atas nama mitra |
| payout approve/mark paid | tidak         | tidak                      | tidak       | ya + bukti           | ya + bukti            |
| role/audit management    | tidak         | tidak                      | tidak       | read audit finansial | ya                    |

Policies memeriksa permission + ownership + status; scope query sebelum pagination/aggregation. Role/permission migration berasal dari vendor publish Spatie setelah pemasangan, bukan membuat schema tiruan. Tests phase berikut harus membuktikan mitra A tidak bisa membaca B dan 403 untuk tindakan finance tanpa permission.

FormRequest untuk write endpoint: validated IDs harus berada dalam ownership scope; qty integer > 0; uang integer nonnegatif; proof private, MIME/ukuran dibatasi; bank account disimpan melalui Laravel encryption dan tidak masuk JSON Inertia, audit atau log. Harga/komisi/status/actor tidak dipercaya dari browser. CSRF, session auth dan rate limiter wajib. Audit hanya metadata yang diizinkan; jangan menyimpan rekening plaintext/password/full proof URL dalam before/after.

## Konfigurasi target (belum diterapkan)

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=honeycommerce
DB_USERNAME=<dedicated_application_role>
DB_PASSWORD=<secret>
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Gunakan database baru/staging dengan role terpisah; jangan mengarahkan test RefreshDatabase ke database aktif. Aktifkan pdo_mysql dan phpredis di runtime yang sesuai. Tetap pakai `database` queue/cache sampai migrasi konfigurasi mendapat persetujuan. Horizon dan gateway bukan dependency Phase 0.

## Gate sebelum fitur finansial aktif

Tarif/cap, minimum payout, masa retur, pembelian sendiri, guest checkout, jadwal payout, atribusi first-valid + TTL 30 hari dan scope produk wajib disepakati. Default aman: produk tidak commission eligible, rule draft, partner pending; tidak ada seed rule 10% atau job otomatis. Multi-level selalu ditolak oleh value object dan database CHECK level=1; aktivasi butuh sign-off dan migration/perubahan kode terpisah.

## Status implementasi Phase 1

Auth/RBAC, UserPolicy, status akun, audit manajemen akses dan UI admin telah diimplementasikan. Permission pengelolaan akses yang dipakai adalah `users.manage-access`. Detail file, command, hasil test dan batas kesiapan PostgreSQL/Redis ada pada [laporan Phase 1](phase-1-report.md). Matriks dan uraian Phase 0 di atas tetap menjadi catatan rancangan awal.

## Status Phase 2 dan keputusan database

MySQL/InnoDB menjadi database target sesuai instruksi pengguna. Koneksi aktif mysql pada server lokal MariaDB 10.4.32 dipertahankan. Database constraint dan trigger disediakan oleh migration add_mysql_commerce_invariants; minimum MySQL 8.0.16 atau MariaDB 10.4. Tidak ada PostgreSQL yang diperlukan untuk menjalankan atau menguji Phase 2. Riwayat verifikasi PostgreSQL pada laporan fase sebelumnya tidak diubah.

Katalog, foto, kategori/varian, cart akun, checkout idempotent, reservasi stok, transfer manual, verifikasi Finance, proses/kirim/terima, pembatalan dan expiry telah diimplementasikan. Diskon saat ini adalah nominal rupiah per unit pada varian, dialokasikan tepat ke setiap baris berdasarkan quantity; tidak ada voucher atau diskon global order. Snapshot referral/rule tetap null sampai Phase 3. Saldo/komisi/payout tidak diaktifkan.

Checkout memakai locking read untuk produk, kategori, varian dan inventory; objek hasil lock dipakai langsung agar tidak membaca snapshot lama pada isolation REPEATABLE READ MySQL. Reservasi tidak mengurangi stok fisik sampai dikirim. Pembatalan/expiry melepas reserved tepat sekali. Bukti submitted menahan expiry sampai review Finance. File bukti privat melalui Policy; public photos hanya format image yang divalidasi.

Lihat [laporan Phase 2](phase-2-report.md) untuk file, command, hasil test, konfigurasi ongkir/rekening dan batas operasional.

## Implementasi finance MVP (27 Oktober 2026)

Domain Partners menyediakan pengajuan, persetujuan, kode referral dan first-valid cookie TTL 30 hari. CheckoutAttribution menyimpan snapshot aturan per order item, sehingga perubahan rule berikutnya tidak berlaku surut. Rule draft harus disimulasikan dan disetujui Finance sebelum publish; tidak ada tarif contoh yang otomatis aktif.

CommissionEngine hanya membuat komisi direct satu level dari pembayaran terverifikasi untuk produk eligible. Komisi pending dilepas setelah delivery dan return window snapshot; fraud hold dan status mitra membatasi pemrosesan. ProcessFinance mengonsumsi outbox dan melakukan catch-up/maturity setiap menit melalui scheduler. Order dapat selesai tanpa referral.

PartnerLedger menghitung saldo dari ledger append-only. Writer mengunci order terlebih dahulu bila terkait order, lalu partner; request/decision payout mengunci partner lalu payout/entry. Semua penulisan dana mitra memakai mutex partner. Payout mengalokasikan nominal secara FIFO ke entitlement komisi; tersedia penarikan sebagian saldo, bukan transfer bank otomatis. Payout yang terkait refund dan belum dibayar dibatalkan; reversal setelah settlement menghasilkan receivable yang di-offset dari penghasilan berikutnya.

Identitas rekonsiliasi adalah `pending + available + reserved + paid - receivable = SUM(amount - reversed_amount)`. Reserve dan settlement juga dicocokkan dengan status payout dan jumlah alokasi. Rekonsiliasi memakai shared partner lock dalam transaksi agar tidak membaca beberapa tahap writer bersamaan.

Tanggal bisnis pertama pada tabel MySQL/MariaDB dikonversi ke DATETIME eksplisit (nilai UTC), menghindari implicit ON UPDATE pada TIMESTAMP MariaDB. Nilai historis yang sudah berubah sebelum migration tidak direkonstruksi otomatis. Migration menambah kontrol finance dan owner FK payout_allocations; rollback menolak atribusi tanpa rule yang tidak cocok dengan invariant versi lama.

CommerceNotifier menyimpan notifikasi pada outbox dalam transaksi domain. Consumer mengantrekan email setelah commit. Penyedia email dapat mengirim ulang saat hasil pengiriman tidak pasti; tidak mengklaim email exactly-once. Local mail tetap log, queue/session/cache tetap konfigurasi database yang ada. Redis sudah dicontohkan dalam .env.example untuk environment yang menyediakan service dan extension.

Lihat phase-3-report.md, phase-4-report.md, phase-5-report.md dan deployment-runbook.md untuk bukti, daftar file dan batas operasional.
