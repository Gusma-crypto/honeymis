# Phase 1 - auth, roles dan authorization

Tanggal: 26 Oktober 2026 (Asia/Jakarta). Status: implementasi aplikasi selesai untuk review; aktivasi runtime PostgreSQL/Redis masih menunggu layanan/kredensial yang siap. Phase 2 belum dimulai.

## Implementasi

- Spatie Laravel Permission 8.3.2 stable terpasang, lock file diperbarui; dependency transitif laravel-package-tools 1.93.3. Tidak mengubah versi Laravel/Fortify/Inertia.
- Migration vendor permission dipublish, migration additive menambah status users active/suspended dengan database enum constraint. Nilai status bukan mass assignable.
- RolePermissionSeeder idempotent mengisi lima role dan 17 permission guard web. Seeder mengembalikan permission tiap role ke matriks source; tidak membuat akun demo/password default dan tidak mengubah assignment user yang ada.
- Customer baru dibuat secara transactional bersama assignment Customer dan audit minimal. Payload roles/permissions/status dari registrasi tidak dipercaya. Tidak ada commission atau wallet entry dari registrasi.
- Laravel UserPolicy mengatur akses daftar/manajemen user, ownership profil, self-change prohibition dan penghapusan akun dengan histori. UserPolicy didaftarkan eksplisit. Module Policies order/wallet/payout ditambahkan ketika model dan endpoint domainnya hadir pada fase pemiliknya.
- Gate integration Spatie dikendalikan provider agar suspended selalu ditolak sebelum pemberian permission. Tidak ada wildcard bypass Super Admin: role itu memiliki permission granular dari seeder.
- Login password menggunakan Fortify callback dengan password hashing/rehash dan Timebox. Middleware memblokir sesi suspended, challenge 2FA suspended, serta hasil login dari jalur lain seperti passkeys. Fitur login/reset/email verification/2FA starter kit dipertahankan.
- Halaman Inertia React pengelolaan akses di route bernama admin.users.index. Update memakai FormRequest, Policy, verified email, konfirmasi password terbaru, throttle dan CSRF web middleware. Wayfinder dipakai untuk navigasi/form. Data daftar dipilih eksplisit tanpa password/MFA secret.
- UpdateUserAccess mengunci row role Super Admin sebagai mutex yang sama untuk seluruh update dan bootstrap, lalu user actor/subject. Policy diulang setelah lock. Role/status + audit teredaksi ditulis dalam satu transaksi, dengan deadlock retry dan idempotency key. Replay payload/actor/subject berbeda ditolak. Super Admin aktif terakhir dan akses diri sendiri terlindungi. Suspensi menghapus remember token; sesi lain ditolak saat request berikut.
- Bootstrap admin pertama hanya untuk akun aktif yang sudah verified, harus memakai --confirm, diaudit dan ditolak di production maupun jika sudah ada Super Admin. Tidak ada akun dipromosikan dalam sesi implementasi ini.
- .env.example disiapkan untuk HoneyCommerce + PostgreSQL + Redis. .env aktif dipertahankan, termasuk secrets dan koneksi lokal sebelumnya.

## Matriks role yang diterapkan

| Role        | Permission utama                                                                                                 |
| ----------- | ---------------------------------------------------------------------------------------------------------------- |
| Super Admin | semua 17 permission eksplisit, termasuk users.manage-access                                                      |
| Admin Toko  | catalog.manage, inventory.manage, orders.manage, partners.manage, reports.view                                   |
| Finance     | payments.verify, refunds.manage, commission-rules.manage, wallets.view, payouts.review, reports.view, audit.view |
| Mitra       | orders.view-own, referrals.view-own, commissions.view-own, wallets.view-own, payouts.request-own                 |
| Customer    | orders.view-own                                                                                                  |

Memiliki role Mitra belum membuat partner approved atau referral eligible; workflow mitra tetap Phase 3. Permission domain adalah fondasi authorization, bukan aktivasi endpoint commerce/finance.

## File ditambahkan

- `app/Actions/Fortify/AuthenticateActiveUser.php`
- `app/Actions/Users/UpdateUserAccess.php`
- `app/Console/Commands/BootstrapAdministrator.php`
- `app/Domains/Shared/Enums/SystemPermission.php`
- `app/Domains/Shared/Enums/SystemRole.php`
- `app/Domains/Shared/Enums/UserStatus.php`
- `app/Http/Controllers/Admin/UserAccessController.php`
- `app/Http/Middleware/EnsureActiveAccount.php`
- `app/Http/Requests/Admin/UpdateUserAccessRequest.php`
- `app/Policies/UserPolicy.php`
- `app/Providers/AuthorizationServiceProvider.php`
- `config/permission.php`
- `database/migrations/2026_10_26_114628_create_permission_tables.php`
- `database/migrations/2026_10_26_114629_add_status_to_users_table.php`
- `database/seeders/RolePermissionSeeder.php`
- `resources/js/pages/admin/users/index.tsx`
- `tests/Feature/AccountSecurityTest.php`
- `tests/Feature/RolePermissionSeederTest.php`
- `tests/Feature/UserAccessTest.php`
- `docs/honeycommerce/phase-1-report.md`

## File diperbarui

- `.env.example`
- `composer.json`
- `composer.lock`
- `app/Actions/Fortify/CreateNewUser.php`
- `app/Models/User.php`
- `app/Providers/FortifyServiceProvider.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Requests/Settings/ProfileDeleteRequest.php`
- `bootstrap/app.php`
- `bootstrap/providers.php`
- `database/factories/UserFactory.php`
- `database/seeders/DatabaseSeeder.php`
- `database/migrations/2026_10_26_111016_create_honey_commerce_finance_tables.php`
- `routes/web.php`
- `resources/js/components/app-sidebar.tsx`
- `resources/js/types/auth.ts`
- `docs/honeycommerce/architecture.md`

Wayfinder menghasilkan ulang files actions/routes pada direktori generated yang sudah diabaikan Git; Vite menghasilkan assets/manifest baru di public/build. Composer menjalankan post-update scripts proyek (package discovery, publish assets dan boost:update). Tidak ada repository Git sehingga daftar file ini menjadi manifest perubahan, bukan git diff.

## Migrasi lokal dan pemulihan kompatibilitas

APP_ENV aktif local, MySQL pada loopback dengan database pengembangan db_mlm. Menjalankan migrate menambahkan schema Phase 0 yang belum diterapkan, lalu schema Phase 1; data users/auth yang sudah ada dipertahankan dan semua user existing memperoleh default status active. RolePermissionSeeder dijalankan; tidak ada production database atau transfer uang yang disentuh.

Dua ketidakcocokan migration finance Phase 0 pada MySQL ditemukan: nama index melebihi batas identifier, dan timestamp created_at pada outbox membutuhkan default yang eksplisit. Migration yang belum berhasil tercatat di runtime lokal diperbaiki dengan nama index pendek dan useCurrent pada created_at. Tabel parsial yang baru dibuat dari percobaan gagal diperiksa kosong sebelum dihapus dalam urutan FK dan dibangun ulang; tidak memakai migrate:fresh/rollback terhadap database aktif. Semua migration lokal kini berstatus Ran. CHECK/trigger khusus PostgreSQL tetap sengaja tidak dipasang pada MySQL.

## Command dan verifikasi

```powershell
php C:/composer/composer.phar require spatie/laravel-permission --no-interaction
php C:/composer/composer.phar show spatie/laravel-permission
php artisan vendor:publish --provider='Spatie\Permission\PermissionServiceProvider' --no-interaction
php artisan migrate --no-interaction
php artisan db:seed --class=RolePermissionSeeder --no-interaction
php artisan migrate:status --no-interaction
php artisan route:list --path=admin --except-vendor --no-interaction
php artisan wayfinder:generate --with-form --no-interaction
php C:/composer/composer.phar audit --no-interaction
npm run build
npm run types:check
npm run check -- resources/js/pages/admin/users/index.tsx resources/js/components/app-sidebar.tsx resources/js/types/auth.ts
php -d memory_limit=512M vendor/bin/phpstan analyse --no-progress --error-format=table
```

Test utama memakai cluster PostgreSQL 17.10 baru terisolasi pada loopback port 55439, database honey_phase1_test, bukan database aktif. Variabel koneksi hanya di shell proses test. PHPUnit RefreshDatabase tidak boleh diarahkan ke database yang datanya ingin disimpan.

```powershell
$env:DB_CONNECTION='pgsql'
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='55439'
$env:DB_DATABASE='honey_phase1_test'
$env:DB_USERNAME='honey_phase1'
$env:DB_PASSWORD=''
$env:DB_URL=''
php -d extension=pdo_pgsql vendor/bin/phpunit
php -d extension=pdo_pgsql artisan migrate:rollback --step=2 --no-interaction
php -d extension=pdo_pgsql artisan migrate --no-interaction
php -d extension=pdo_pgsql artisan db:seed --class=RolePermissionSeeder --no-interaction
```

| Pemeriksaan                                                                           | Hasil                                                                                         |
| ------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| Full suite PostgreSQL final                                                           | 164 tests, 395 assertions, seluruhnya lulus, tanpa skip                                       |
| Full suite SQLite saat pemeriksaan awal                                               | 144 lulus, 19 PostgreSQL-only skip, 374 assertions                                            |
| Rollback 2 migration Phase 1, migrate ulang, seed dua kali pada PostgreSQL terisolasi | berhasil                                                                                      |
| Migrasi + seeding database pengembangan lokal                                         | berhasil; migrate:status semua Ran                                                            |
| PHPStan level 7 seluruh proyek                                                        | 0 error                                                                                       |
| TypeScript tsc --noEmit                                                               | lulus                                                                                         |
| Frontend build                                                                        | lulus; ada peringatan optional fontaine, tidak menambah dependency yang tidak diperlukan      |
| Scoped frontend format/lint                                                           | lulus, tanpa warning/error                                                                    |
| Composer audit                                                                        | tidak ada security vulnerability advisories                                                   |
| Pint file PHP yang diubah                                                             | dijalankan dan lolos; --dirty tidak tersedia tanpa Git, sehingga memakai scope file eksplisit |

`npm run check` global masih melaporkan formatting pada PRD referensi dan dokumen Phase 0 yang sudah ada. PRD referensi dipertahankan; architecture.md yang diperbarui diformat sesuai formatter; check scoped untuk seluruh TS/TSX yang berubah lulus. Tidak ada klaim global check sudah hijau.

Tests Phase 1 mencakup matriks per-role, login/sesi/2FA suspended, post-login account guard, ownership Policy, credential confirmation, verified email, penolakan payload invalid/direct permissions, idempotency audit dan konflik replay, last Super Admin, rollback role ketika data seed tidak tersedia, bootstrap safety, data serialization dan penolakan self-delete staff. Existing starter-kit tests tetap lulus. Belum ada klaim browser E2E atau stress-test concurrent role writes.

## Risiko dan langkah menjalankan

1. Aktivasi runtime PostgreSQL/Redis belum dilakukan: Redis server/extension tidak tersedia terverifikasi dan kredensial PostgreSQL aplikasi belum diberikan. Jangan menyalin .env.example ke .env aktif sebelum menyiapkan database/Redis/phpredis/pdo_pgsql. Template sudah siap; active runtime tetap bekerja dengan database/cache/queue lokal yang lama. Fitur finansial tetap belum dibuka dan tidak boleh diproduksikan di MySQL tanpa PostgreSQL invariants.
2. 2FA starter kit tersedia dan diuji, tetapi belum diwajibkan per-role. Wajibkan/enroll admin dan finance sebagai gate operasional sebelum produksi. Mutasi akses memerlukan konfirmasi password terbaru.
3. Perubahan role operasional harus melalui UpdateUserAccess. Command mutasi role bawaan vendor, raw syncRoles atau perubahan permissions langsung oleh operator database berada di luar workflow Policy/audit aplikasi; batasi akses CLI/DB dan jangan pakai untuk operasi rutin.
4. Seeder role merupakan konfigurasi otoritas aplikasi. Perubahan matriks permission harus code-reviewed dan seed dijalankan terkontrol, bukan dipicu dari request publik.
5. Histori audit/commerce memakai FK restrict dan memerlukan retensi/anonymization; self-delete ditolak untuk staff/partner serta user yang mempunyai profil commerce/histori actor agar tidak merusak histori.
6. User yang sudah ada tidak otomatis diberi role atau dipromosikan. Pengelola harus memilih akun verified untuk bootstrap; tidak ada password default atau admin otomatis.

Untuk admin pertama pada development, daftarkan/verifikasi akun melalui auth yang sudah ada, lalu pilih email akun tersebut secara eksplisit:

```powershell
php artisan honey:bootstrap-admin email-akun-terverifikasi --confirm --no-interaction
```

Perintah ini belum dijalankan pada akun pengguna. Setelah grant, login dan gunakan menu Akses pengguna; konfirmasi password diminta bila belum baru. Jangan menjalankan command ini pada production; command memang menolaknya.

Langkah berikut: review Phase 1, selesaikan kesiapan runtime PostgreSQL/Redis, lalu minta persetujuan Phase 2 (catalog + cart + checkout). Tidak deploy ke produksi, tidak mengirim uang, dan tidak memulai Phase 2.

Cluster PostgreSQL test sudah dihentikan dan direktori sementara dibersihkan setelah verifikasi. Layanan/database pengembangan aktif tidak dihentikan.
