# HoneyCommerce ? Panduan operasi dan persiapan deployment

Status: panduan dan QA lokal, **bukan deployment produksi**. MySQL tetap target database. Local driver mysql memakai MariaDB 10.4.32; CI disiapkan untuk MySQL 8.4, belum dijalankan pada remote runner. Sebelum produksi pilih server database yang masih didukung dan jalankan suite terhadap versi tersebut.

## Menjalankan lokal

.env yang ada dipertahankan; jangan menggantinya dengan .env.example. PHP 8.4 dan dependency lock saat ini dipakai. Tidak ada dependency baru pada Phase 3?5.

```powershell
php artisan migrate --no-interaction
php artisan db:seed --class=RolePermissionSeeder --no-interaction
npm run build
composer run dev
```

composer run dev membangun asset satu kali saat startup, lalu menjalankan web server, queue worker dan scheduler tanpa server Vite. Mode ini lebih ringan untuk mencoba aplikasi: browser memakai file public/build dan mengabaikan hot file Vite melalui COMMERCE_USE_BUILT_ASSETS=true pada proses Composer. Untuk mengedit frontend dengan hot reload, gunakan composer run dev:hot; mode itu menjalankan Vite dan menonaktifkan flag built assets. Setelah perubahan frontend pada mode ringan, jalankan npm run build atau mulai ulang composer run dev. Jika memakai proses terpisah, jalankan php artisan serve, npm run dev, php artisan queue:work --tries=3 dan php artisan schedule:work. Jangan menjalankan dua scheduler yang tidak berbagi lock store.

Scheduler menjalankan honey:expire-orders dan honey:process-finance per menit, serta honey:reconcile-finance harian. Pemrosesan finance menulis komisi/release/penyelesaian order dan outbox; jalankan hanya pada environment yang diotorisasi. Rekonsiliasi boleh dijalankan manual dengan php artisan honey:reconcile-finance; exit nonzero berarti perlu investigasi.

Akun pengelola tidak dipilih/diangkat otomatis. Buat/identifikasi akun lokal terverifikasi, lalu operator yang berwenang dapat memakai command lokal honey:bootstrap-admin dengan email miliknya dan --confirm (lihat php artisan honey:bootstrap-admin --help). Jangan memakai alamat contoh atau mempromosikan customer tanpa verifikasi identitas. Store Admin/Finance diatur melalui halaman akses pengguna setelah konfirmasi password.

## Konfigurasi sebelum transaksi riil

| Variabel                      | Default development | Tindakan pemilik                          |
| ----------------------------- | ------------------- | ----------------------------------------- |
| COMMERCE_CONTACT              | kosong              | Isi kontak toko sah                       |
| COMMERCE_PAYMENT_INSTRUCTIONS | hubungi pengelola   | Isi instruksi rekening resmi              |
| COMMERCE_SHIPPING_FLAT_IDR    | 20000               | Setujui tarif dan cakupan ongkir          |
| COMMISSION_RETURN_DAYS        | 7                   | Setujui masa tunggu komisi                |
| COMMISSION_BUDGET_BPS         | 3000                | Setujui ceiling margin; bukan tarif aktif |
| MINIMUM_PAYOUT_IDR            | 50000               | Setujui minimum payout                    |
| MAIL_MAILER                   | log lokal           | Konfigurasi dan uji SMTP pada staging     |

Finance membuat aturan draft dengan rate bps atau fixed IDR, cap, scope dan periode; simulasi lalu publish dengan acknowledgment. Tarif tidak aktif sebelum publish. Produk harus ditandai eligible. Perubahan aturan tidak mengubah snapshot order lama. FinanceSandboxSeeder opsional hanya untuk local/testing dan menghasilkan draft nol yang wajib diisi melalui draft baru; tidak mengaktifkan tarif, saldo, bonus atau payout.

Pengaturan operasional ditampilkan read-only pada halaman konfigurasi toko; perubahan environment dikelola operator. .env.example mencontohkan Redis untuk queue/cache/session. Runtime lokal yang sudah ada tetap database. Redis server/extension dan kredensial harus disiapkan pada environment tujuan, kemudian diuji; tidak diprovisikan otomatis di komputer ini.

## Verifikasi sebelum release

Gunakan database test tersendiri dengan suffix _test. RefreshDatabase/DatabaseMigrations bersifat destruktif terhadap database test. Jangan pakai db_mlm atau database operasional.

```powershell
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='honeycommerce_phase2_test'
php vendor/bin/phpunit -c phpunit.mysql.xml
php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
npm run check
npm run types:check
npm run build
```

Setelah test, tutup shell atau hapus override DB_CONNECTION/DB_DATABASE dari proses sebelum menjalankan command operasional. CI di .github/workflows/tests.yml memakai database honeycommerce_test, bukan konfigurasi produksi. Restore test memerlukan mysql/mysqldump CLI dan izin membuat database restore acak berakhiran _test.

## Deployment setelah persetujuan eksplisit

Siapkan staging terpisah lebih dahulu. Verifikasi PHP extension, private storage, MySQL, Redis, SMTP, HTTPS/cookie secure, APP_URL, APP_ENV=production dan APP_DEBUG=false. Kunci APP_KEY harus stabil dan disimpan di secret manager. Bank terenkripsi tidak dapat dibuka jika kunci hilang.

Pipeline harus memasang dependency dari lock, membangun asset, menjalankan test lalu menyiapkan release. Setelah pemilik menyetujui deployment, operator mengambil backup terverifikasi, mengatur jendela migration, menjalankan migrate --force dan optimize, mengaktifkan release, lalu merestart worker. Langkah ini **belum dieksekusi** terhadap produksi. Hindari migrate:fresh, db:wipe atau seeder sandbox pada data operasional.

Worker permanen: php artisan queue:work --sleep=3 --tries=3 --timeout=90 melalui service manager. Scheduler: satu pemanggilan php artisan schedule:run setiap menit melalui cron/service yang diawasi. Untuk beberapa host gunakan cache/lock store bersama dan pengaturan scheduler satu server sesuai topologi yang diuji. Pantau exit code rekonsiliasi, scheduler failures, outbox pending/umur tertua, failed_jobs, order proof submitted lama dan error storage/email. Email bersifat antrean; delivery provider tidak dijamin exactly-once.

## Backup, restore dan pemulihan

Backup harian terenkripsi mencakup SQL (schema, data, trigger/routine), private bukti transfer serta APP_KEY/secret yang disimpan terpisah. Gunakan akun backup dengan least privilege; password lewat secret-managed defaults file, bukan argumen CLI/log. mysqldump --single-transaction memerlukan tabel InnoDB dan tidak boleh bersamaan DDL. Simpan backup di luar web root dan host aplikasi, tetapkan retensi/RPO/RTO bersama pemilik.

Restore ke database/environment kosong terpisah, impor schema/data/trigger, pulihkan file privat dan APP_KEY, lalu bandingkan totals, jalankan rekonsiliasi, uji larangan UPDATE/DELETE ledger, akses bukti, serta dekripsi rekening dengan akun berwenang. Local acceptance test telah menjalankan dump/restore tujuh tabel dan trigger; restore staging penuh dengan private files serta SMTP/Redis masih perlu environment staging.

Sesudah transaksi keuangan berjalan, jangan melakukan rollback migration secara buta karena kolom adjustment dan allocation adalah riwayat. Gunakan perbaikan maju atau pemulihan backup yang telah disetujui dengan write freeze, evaluasi kehilangan data dan rekonsiliasi. Down migration menolak atribusi tanpa rule yang tidak kompatibel dengan invariant sebelumnya.

## Gate bisnis yang masih terbuka

UAT pemilik, tarif/cap/masa retur/minimum payout nyata, rekening resmi, identitas/klaim legal produk, ketentuan retur/privasi final, domain/SSL, monitoring dan restore staging memerlukan pengaturan dan persetujuan pemilik. Halaman informasi publik menyediakan teks awal dan menandai hal yang memerlukan keputusan tersebut. Tidak ada sign-off legal/finance yang diasumsikan.

Multi-level, gateway, kurir API, WhatsApp, voucher, rank, batch kedaluwarsa dan multi-gudang adalah scope lanjutan PRD, tidak diaktifkan sebagai bagian MVP. Tidak ada uang dikirim oleh aplikasi.

## Mode produksi lokal yang sudah diaktifkan (28 Oktober 2026)

Target telah dipilih pemilik: hanya komputer ini, tanpa publikasi internet. Masuk melalui http://127.0.0.1:8000/login. Gunakan akun administrator yang telah dibuat; tidak perlu menjalankan bootstrap admin lagi. APP_ENV=production membuat bootstrap non-production ditolak sebagaimana mestinya.

```powershell
# Hanya bila kode atau frontend baru berubah; membangun asset dan menyegarkan cache:
php C:/composer/composer.phar run production:prepare
# Menjalankan web pada 127.0.0.1:8000, queue dan scheduler:
php C:/composer/composer.phar run production:local
# Pemeriksaan read-only:
php artisan honey:check-production --local
php artisan honey:reconcile-finance
```

Biarkan terminal startup berjalan. Setelah komputer/reboot atau terminal berhenti, jalankan production:local kembali. Proses ini belum dipasang sebagai Windows Service atau tugas otomatis saat boot. Jika server sudah berjalan pada port 8000, gunakan yang sudah berjalan atau hentikan launcher lamanya sebelum memulai yang baru; jangan menggandakan scheduler/worker tanpa alasan.

Queue/cache/session memakai database yang sudah ada. Email tetap ditulis ke laravel.log, bukan dikirim ke Gmail; ini memungkinkan pengujian lokal tanpa SMTP. Session terenkripsi; session lama dapat meminta login ulang setelah pergantian mode. APP_KEY dan kredensial database tidak diganti. Jangan menjalankan key:generate atau composer setup pada instalasi berisi data terenkripsi, karena setup bawaan membuat key baru.

Status server/cache diperiksa oleh honey:check-production --local; command ini tidak mengesahkan konfigurasi bisnis atau deployment publik. Cookie secure sengaja false untuk loopback HTTP saja. Pada server publik wajib siapkan HTTPS, secure cookie, email nyata, backup/monitoring dan keputusan bisnis/legal yang disebut di atas.

### Menjalankan test ketika cache produksi aktif

Config cache produksi harus dilewati pada proses test agar PHPUnit membaca database test. Guard TestCase tetap menolak database yang tidak berakhiran _test. Pakai terminal test terpisah dan override path cache hanya pada proses tersebut; jangan membersihkan cache aplikasi produksi yang sedang berjalan hanya untuk test.

```powershell
$env:APP_CONFIG_CACHE='storage/framework/cache/phpunit-config.php'
$env:APP_ROUTES_CACHE='storage/framework/cache/phpunit-routes.php'
$env:APP_EVENTS_CACHE='storage/framework/cache/phpunit-events.php'
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='honeycommerce_phase2_test'
php vendor/bin/phpunit -c phpunit.mysql.xml
```

Database test akan dibersihkan oleh test suite. Tutup terminal test setelah selesai agar override tidak terbawa ke command operasional. Untuk kembali mengembangkan dalam mode local, hentikan launcher, ubah APP_ENV=local dan APP_DEBUG=true pada .env, jalankan config:clear/route:clear/event:clear/view:clear, lalu gunakan dev atau dev:hot. Untuk kembali ke produksi lokal, kembalikan flag produksi dan jalankan production:prepare.
