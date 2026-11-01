# HoneyCommerce

## Demo Ubuntu dengan satu perintah

Setelah Docker Engine, Docker Compose plugin v2, OpenSSL, dan kode proyek tersedia pada Ubuntu:

```bash
sh deploy.sh --url http://IP_SERVER:8000
```

Ganti `IP_SERVER` dengan IP Ubuntu. Untuk demo pada komputer sendiri cukup `sh deploy.sh`. Script membuat `.env.docker` dengan permission 600, key aplikasi, password MySQL/root yang berbeda, dan password akun demo secara acak. Setelah itu script membangun backend/frontend, menunggu database, menghentikan aplikasi lama, menjalankan migration dan seed melalui startup app, lalu menjalankan web, worker, scheduler, pemeriksaan kesehatan, dan rekonsiliasi finance. Docker tidak dipasang otomatis oleh script.

Untuk melihat password bersama akun demo:

```bash
grep '^DEMO_PASSWORD=' .env.docker
```

Admin: `admin@honey-demo.test`; finance: `finance@honey-demo.test`; pembeli: `customer@honey-demo.test`. Mitra utama memakai `partner@honey-demo.test`, sembilan mitra lain memakai `partner2@honey-demo.test` sampai `partner10@honey-demo.test`. Semua akun sudah terverifikasi.

| Mitra     | Sponsor  | Kedalaman dari mitra utama | Rank simulasi |
| --------- | -------- | -------------------------- | ------------- |
| partner   | —        | 0                          | Platinum      |
| partner2  | partner  | 1                          | Gold          |
| partner3  | partner2 | 2                          | Silver        |
| partner4  | partner3 | 3                          | Bronze        |
| partner5  | partner4 | 4                          | Member        |
| partner6  | partner5 | 5                          | Member        |
| partner7  | partner  | 1                          | Silver        |
| partner8  | partner7 | 2                          | Bronze        |
| partner9  | partner7 | 2                          | Member        |
| partner10 | partner2 | 2                          | Bronze        |

Rank dipasang melalui override yang diaudit untuk simulasi, bukan klaim pencapaian omzet. Katalog, gambar, order pembayaran simulasi, dan komisi tertunda juga disiapkan. Seeder dapat dijalankan ulang tanpa menggandakan akun/order atau mengganti password, sponsor, dan override rank yang sudah ada.

Untuk update fitur dari repository Git pada branch aktif:

```bash
sh deploy.sh --update
```

Script menolak perubahan kode yang belum disimpan ke commit dan hanya melakukan `git pull --ff-only`. Jika kode diperbarui melalui upload, jalankan `sh deploy.sh` kembali. Keduanya membangun ulang frontend/backend dan menerapkan migration baru, mempertahankan `.env.docker`, key, dan volume data. Ada jeda layanan saat migration dan restart; script tidak menghapus volume. Jika build gagal, aplikasi lama belum dihentikan. Jika startup gagal, script menampilkan log untuk diperbaiki lalu dijalankan ulang.

Script ini khusus **demo** dengan `APP_ENV=local`, `APP_DEBUG=false`, dan `APP_SEED_DEMO=true`; environment produksi yang sudah ada tidak dikonversi otomatis. Pada instalasi baru script mengaktifkan `DEMO_ALLOW_REMOTE=true` dan bind `0.0.0.0:8000` agar bisa diakses melalui IP Ubuntu. Untuk `.env.docker` lama, pertahankan password database yang sudah dipakai dan isi variabel kosong secara manual; gunakan `--url` untuk mengizinkan hostname demo jarak jauh dan atur `DOCKER_HTTP_BIND=0.0.0.0` bila perlu. Key kosong pada instalasi demo lama tetap memakai key tersimpan di volume. Jangan menghapus `.env.docker` untuk memperbarui instalasi yang sudah berisi data. Batasi akses server demo ke peserta yang dituju; pengaturan HTTPS tetap dikelola di reverse proxy.

## Akun demo lokal

Akun berikut dan data katalog/transaksi contoh dibuat otomatis untuk Docker demo:

| Peran       | Email                      |
| ----------- | -------------------------- |
| Super admin | `admin@honey-demo.test`    |
| Finance     | `finance@honey-demo.test`  |
| Mitra       | `partner@honey-demo.test`  |
| Pembeli     | `customer@honey-demo.test` |

Semua memakai nilai `DEMO_PASSWORD` di `.env.docker`. Password contoh memang mudah untuk presentasi lokal; jangan gunakan ulang atau membuka konfigurasi ini ke jaringan publik. Foto produk memakai aset madu demo yang sudah ada. Order contoh menunjukkan satu pembayaran simulasi dengan komisi MLM tertunda dan satu order dibatalkan; bukan transaksi atau bukti pembayaran riil.

Program MLM dapat diatur melalui **Admin → Program MLM**: target rank bulanan, tarif lima generasi, dan referral 5% untuk transaksi pertama pelanggan. Tarif komisi direct 15–20% dikelola di **Aturan komisi**. Sistem menahan perubahan yang melewati margin produk; komisi hanya muncul setelah pembayaran diverifikasi dan direkonsiliasi saat refund. Omzet tim menghitung downline, tidak termasuk omzet mitra sendiri.

## Menjalankan dengan Docker lokal

Salin konfigurasi contoh dan ganti kedua password database lokal sebelum dijalankan. `DEMO_PASSWORD` boleh tetap sederhana hanya selama demo lokal:

```powershell
Copy-Item .env.docker.example .env.docker
docker compose --env-file .env.docker up -d --build
```

Setelah container sehat, buka [http://localhost:8000](http://localhost:8000). MySQL dan Redis hanya tersedia di jaringan internal Docker; web hanya bind ke loopback. `APP_KEY` dibuat otomatis dan disimpan pada volume Docker. Semua akun demo sudah terverifikasi; log email/notifikasi lain dapat dilihat dengan:

```powershell
docker compose --env-file .env.docker logs -f app
```

Untuk reset seluruh data demo termasuk database dan unggahan, jalankan `docker compose --env-file .env.docker down -v`. Pembayaran pada konfigurasi ini hanya simulasi lokal.

## Menjalankan aplikasi

```powershell
php C:/composer/composer.phar run production:local
```

Jika kode atau frontend berubah, bangun ulang asset dan cache terlebih dahulu:

```powershell
php C:/composer/composer.phar run production:prepare
```

Panduan operasional: [deployment-runbook.md](docs/honeycommerce/deployment-runbook.md).

## Deploy Docker di Ubuntu

Gunakan Docker Engine dan Docker Compose plugin (`docker compose`) yang sudah terpasang pada server Ubuntu. Jalankan dari direktori proyek. PHP, Composer, Node, MySQL, dan Redis dijalankan di Docker; tidak perlu dipasang di host.

```bash
cp .env.docker.production.example .env.docker
chmod 600 .env.docker
nano .env.docker
```

Isi `DB_PASSWORD` dan `MYSQL_ROOT_PASSWORD` dengan password berbeda yang kuat, serta `APP_URL` dengan alamat server/domain. Untuk nilai yang mengandung `$` atau `#`, gunakan petik tunggal. Pada instalasi baru, buat `APP_KEY` sekali:

```bash
docker run --rm php:8.4-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Salin hasilnya ke `APP_KEY`. Untuk memindahkan instalasi lama, gunakan `APP_KEY` lama dan pulihkan database serta storage dari backup; jangan membuat kunci pengganti. Simpan kunci di backup terpisah.

```bash
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs --tail=100 app web worker scheduler
curl --fail http://127.0.0.1/up
```

Startup app menjalankan migration sebelum worker/scheduler mulai. Produksi tidak membuat akun atau transaksi demo. Untuk instalasi baru, inisialisasi role/permission secara eksplisit:

```bash
docker compose --env-file .env.docker exec app php artisan db:seed --class=RolePermissionSeeder --force --no-interaction
```

Akun administrator produksi harus diprovisikan melalui prosedur operator yang berwenang; `honey:bootstrap-admin` hanya tersedia di environment non-produksi. Konfigurasi ini tidak otomatis mempromosikan akun pengguna.

Contoh produksi membuka HTTP port 80. Sebelum login publik/transaksi riil, siapkan HTTPS pada reverse proxy, ubah `APP_URL` menjadi domain HTTPS dan `SESSION_SECURE_COOKIE=true`. Jika reverse proxy berjalan pada host yang sama, gunakan `DOCKER_HTTP_BIND=127.0.0.1` dan `DOCKER_HTTP_PORT=8000`; teruskan Host asli dan tangani terminasi HTTPS/proxy tepercaya sesuai topologi server. TLS belum disediakan oleh Compose ini. Isi konfigurasi SMTP dan kontak/instruksi pembayaran yang benar sebelum dipakai operasional.

Database, Redis, serta storage disimpan pada named volume. Untuk pembaruan, backup database, storage, dan kunci terlebih dahulu, lalu jalankan:

```bash
docker compose --env-file .env.docker build
docker compose --env-file .env.docker up -d --force-recreate
docker compose --env-file .env.docker ps
curl --fail http://127.0.0.1/up
```

Pembaruan ini memerlukan jendela maintenance; bukan deployment tanpa downtime. Jangan gunakan `down -v` pada server berisi data. Jangan menjalankan scheduler tambahan di cron host karena service `scheduler` sudah berjalan. Password MySQL pada environment hanya berlaku saat volume database pertama dibuat; perubahan password pada database lama perlu dilakukan secara terpisah dan diselaraskan dengan environment.
