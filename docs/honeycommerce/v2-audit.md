# Audit Arsitektur dan Gap HoneyCommerce V2

Tanggal: 1 November 2026. Dokumen ini mencatat keadaan sebelum implementasi sprint V2; bukan pernyataan bahwa seluruh V2 selesai.

## 1. Arsitektur aktual

- Aplikasi existing Laravel 13.38.0/PHP 8.4, monolit modular di `app/Domains`. Tidak memerlukan scaffold baru.
- React terpasang 19.3.1, Inertia React 3.9.1, Inertia Laravel 3.5.2, TypeScript 5.9.3, Tailwind 4.3.3. TypeScript menggunakan `strict`, ESNext, resolusi bundler, JSX React, alias `@/`.
- Routing web bernama di `routes/web.php`, autentikasi/pengaturan di `routes/settings.php`, scheduler di `routes/console.php`. Inertia menjadi kontrak halaman; Wayfinder menghasilkan pemanggil route TypeScript. JSON terbatas untuk kebutuhan interaktif dapat memakai session/CSRF yang sama.
- Fortify 1.41.0 menangani password, verifikasi email, 2FA, passkey. Middleware memblokir akun nonaktif. Login password baru mencatat waktu konfirmasi; middleware konfirmasi password tetap melindungi area sensitif.
- Spatie Permission 8.3.2 dan policies aplikasi membatasi super admin, admin toko, finance, mitra, pelanggan. Izin melihat jaringan tidak otomatis berarti izin melihat keuangan.
- Koneksi aktif menggunakan driver MySQL, database lokal `db_mlm`. CI menyediakan MySQL 8.4. `db:show` lokal gagal pada metadata `performance_schema.session_status`; versi server lokal belum terverifikasi, jangan menyamakan server lokal dengan MySQL CI.
- Seluruh 19 migration existing tercatat sudah dijalankan. Ada guards spesifik MySQL/MariaDB dan PostgreSQL serta suite SQLite. PostgreSQL bukan target deployment aktif.
- UI admin menggunakan layout bersama navy/biru/emas; storefront dan portal mitra tetap memiliki alur sendiri.
- PHPUnit 12.5.40, PHPStan/Larastan, Pint, pemeriksaan TypeScript/lint/build, serta tes UI Node sudah tersedia. CI memakai `phpunit.mysql.xml` dan database khusus `_test`.

## 2. Alur mitra/referral/keuangan aktual

1. Pelanggan mendaftar melalui Fortify, mendapat peran customer, dan memverifikasi email. Gender opsional tersimpan; foto profil boleh diunggah. Avatar kartun menjadi fallback lokal tanpa layanan pihak ketiga.
2. Pengajuan mitra memakai kode sponsor aktif. Satu akun hanya boleh mempunyai satu `partners` dan satu kode referral unik. Admin dapat menempatkan akun terverifikasi sebagai cabang utama, di bawah, atau sejajar mitra lain. Status awal pending. Aktivasi tidak membuat bonus.
3. `partners.sponsor_partner_id` adalah parent unilevel sekaligus upline langsung. Root tanpa sponsor berada di bawah root perusahaan virtual, bukan anggota pembayar biaya masuk. Tidak perlu menduplikasi kolom sponsor/upline dengan arti sama.
4. Tautan referral membuat token acak yang hash-nya disimpan server. Cookie/session menyimpan token, attribution pertama yang valid dipertahankan sampai kedaluwarsa, self-referral dan mitra nonaktif ditolak.
5. Checkout memvalidasi ulang harga/stok, menyimpan snapshot item, biaya, diskon, rule komisi dan rantai generasi, lalu mereservasi stok dengan idempotency key.
6. Pembayaran adalah transfer manual: bukti diunggah, finance memverifikasi nominal dan state. Tidak ada provider webhook aktif. Keputusan pengguna V2: pertahankan transfer manual dahulu; jangan membuat webhook simulasi yang ditampilkan sebagai integrasi riil.
7. Komisi direct dan generasi dihitung pada order terbayar valid, menggunakan integer rupiah dan basis points, snapshot rule, unique keys, transaksi serta row locks. Dompet memakai ledger append-only, bukan saldo yang bisa diedit.
8. Keputusan referral terdahulu tetap berlaku: 5% transaksi pertama **menggantikan** generasi pertama 4%, tidak ditumpuk. Tarif generasi reguler awal berjumlah 8%. Batas tarif generasi harus dipisahkan dari anggaran total komisi direct/referral.
9. Komisi pending dilepas setelah pengiriman dan masa retur. Refund parsial membalik hak komisi secara proporsional, membatalkan reservasi payout terbuka dan mencatat piutang jika dana sudah dibayar.
10. Payout mengunci mitra, mengalokasikan komisi available, mereservasi ledger, meminta approval finance dan bukti/reference transfer sebelum status paid. Retry dilindungi idempotency dan referensi unik.
11. Rank bulanan dihitung dari produk dibayar penuh, status valid, tidak fraud hold, dikurangi refund. Omzet tim menghitung keturunan, tidak memasukkan omzet sendiri. Scheduler melakukan catch-up.

## 3. Klasifikasi dan gap per modul

| Modul                                    | Klasifikasi               | Keadaan dan kebutuhan V2                                                                                                                                                          |
| ---------------------------------------- | ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel/React/Inertia/Wayfinder/Tailwind | KEEP                      | Pertahankan framework, build, route dan komponen yang bekerja.                                                                                                                    |
| Auth, akun, RBAC, policies               | KEEP / EXTEND             | Pertahankan akun; audit penerjemahan UI dan izin setiap endpoint baru.                                                                                                            |
| Catalog, kategori, varian, gambar        | EXTEND                    | CRUD/search/status/stok sudah ada; belum lengkap grid/list, harga member, bundling, import Excel dan ekspor terkontrol. Kategori tetap dari DB.                                   |
| Cart, checkout, stok, shipping           | KEEP                      | Jangan mewajibkan membership; pertahankan fingerprint quote, reservasi dan adapter RajaOngkir.                                                                                    |
| Order/payment manual                     | KEEP / EXTEND             | Transfer manual bekerja; chargeback dan gateway belum menjadi workflow terpisah. Webhook menunggu keputusan integrasi provider.                                                   |
| Partner/parent/referral                  | KEEP / EXTEND             | Gunakan `partners`; harden invalid ancestry dan audit. Sponsor mitra yang disetujui tidak boleh dipindahkan diam-diam.                                                            |
| Genealogy                                | REFACTOR                  | `NetworkSummary` masih mengambil seluruh graph; UI membatasi gambar tetapi payload tetap seluruh jaringan. Perlu endpoint berizin dengan cursor anak/cabang dan pencarian server. |
| Rank                                     | EXTEND                    | Projection bulanan ada; belum ada histori keputusan append-only, override beralasan sampai dicabut, maintenance dan periode evaluasi yang bisa dikonfigurasi.                     |
| Volume                                   | EXTEND                    | Saat ini dihitung ulang dari transaksi; belum ada ledger volume event/reversal yang dapat direkonsiliasi. Backfill harus eksplisit dan idempotent.                                |
| CommissionEngine                         | KEEP / REFACTOR           | Pertahankan snapshot, Idr, refund dan ledger; pisahkan calculator dry-run reusable, batas generasi dan anggaran generation/direct/referral.                                       |
| Konfigurasi MLM                          | EXTEND                    | Target/rate bisa diubah dan diaudit; perlu max generasi, holding, payout minimum, maintenance, histori versi konfigurasi penuh dan bonus opsional nonaktif.                       |
| Wallet/payout                            | KEEP / EXTEND             | Reservasi, idempotency, bukti dan audit bekerja; verifikasi rekening formal belum tersedia. Tidak menandai paid dari klik approval saja.                                          |
| Dashboard admin                          | EXTEND                    | Data DB dan layout ada; metrik gabungan, tren akurat refund, distribusi komisi dan top performer perlu dituntaskan.                                                               |
| Dashboard komisi                         | REFACTOR / EXTEND         | Saat ini menu Komisi terutama editor rule. Butuh ringkasan, history, per-level, tim, payout dan simulator yang memakai calculator engine.                                         |
| Marketing/promosi                        | EXTEND                    | Token referral ada; belum ada kampanye, event kunjungan terukur, materi, QR, konversi dan activity feed. Broadcast tidak boleh mengirim pesan tanpa otorisasi pengguna.           |
| Portal mitra                             | EXTEND                    | Rank, omzet, saldo, referral, jaringan/payout dasar ada; perlu halaman navigasi dan tools lengkap dengan scope keturunan sendiri.                                                 |
| Storefront                               | KEEP                      | Pertahankan belanja tanpa MLM, product detail, order history dan referral opsional.                                                                                               |
| Model kosong `app/Models/Domains/*`      | DEPRECATE (rencana)       | Ada kelas duplikat kosong. Audit referensi/autoload sebelum menghapus; belum dihapus atau dipakai sebagai model baru.                                                             |
| Dokumentasi fase lama/direct-only        | DEPRECATE (teks historis) | Tetap menjadi riwayat; tandai scope historis, hindari menggunakannya sebagai spesifikasi V2.                                                                                      |
| Tes/CI                                   | KEEP / EXTEND             | Suite existing wajib dipertahankan. SQLite tidak membuktikan locking/trigger MySQL; jalankan suite MySQL khusus sebelum staging.                                                  |

## 4. Strategi database

- Gunakan tabel existing: `partners`, `commission_rules`, `commission_entries`, `wallet_ledger_entries`, `payout_requests`, `payout_allocations`, `audit_logs`, `outbox_messages`, `orders`, `order_items`, `payments`, `refunds`.
- Tambahkan histori rank, override rank, versi konfigurasi dan volume ledger hanya untuk konsep yang belum ada. Jangan membuat ledger/saldo paralel yang saling bertentangan.
- Kolom baru nullable atau mempunyai default kompatibel. Jangan rename/drop tabel, reset, truncate, migrate:fresh, atau backfill nilai finansial secara otomatis.
- Backfill ancestry/volume harus memiliki dry-run, ukuran batch, idempotency, laporan reconciliation dan rollback operasional. Data historis tidak boleh dihitung ulang dengan tarif baru.
- Indeks mengikuti filter nyata: sponsor/status/id, member/period/type, order item/event key, status/tanggal, parent/cursor.
- Histori/ledger append-only; reversal merupakan entri koreksi, bukan menghapus transaksi. Rollback migration harus menolak penghapusan histori yang sudah dipakai.
- Deployment bertahap: tambah schema → deploy kode kompatibel → verifikasi staging → jalankan backfill yang disetujui → rekonsiliasi → aktifkan fitur.

## 5. Risiko dan dependensi

- Kode saat audit mempunyai banyak perubahan belum committed. Tidak melakukan reset atau menimpa pekerjaan pengguna.
- Trigger/lock berbeda antara SQLite, MySQL dan MariaDB. Pengujian concurrency memerlukan database khusus `_test`, bukan database aplikasi.
- Histori rank existing adalah projection yang bisa berubah. Jangan menyebutnya audit rank lengkap sebelum log keputusan ditambahkan.
- Rule settings current-row bersifat mutable; snapshot checkout melindungi komisi existing, tetapi arsip konfigurasi penuh masih diperlukan.
- Batas lima generasi payout tidak membatasi kedalaman genealogy. Jumlah referral langsung tidak dibatasi tiga hanya untuk menyamai gambar.
- Peta sebaran tidak dapat benar tanpa data domisili dengan izin yang sesuai. Jangan mengambil alamat pengiriman pelanggan sebagai lokasi mitra atau menggambar marker fiktif.
- Referensi yang tersedia di percakapan adalah dashboard utama dan jaringan. Referensi Produk/Komisi/Marketing perlu dicocokkan ketika tersedia; tidak mengklaim kesamaan piksel yang belum diperiksa.
- Import Excel/QR harus memeriksa dependencies terpasang. Dependency baru memerlukan persetujuan; jangan memasang parser/generator diam-diam.
- Tidak ada kredensial provider pembayaran; tidak mengarang verifikasi signature/provider settlement.
- Pengguna menetapkan override rank berlaku sampai dicabut, bukan otomatis berakhir bulanan. Pencabutan harus diaudit dan kembali ke hasil evaluasi normal.
- Legal komersial belum dinyatakan selesai. Perizinan penjualan langsung, marketing plan, perlindungan konsumen dan kepatuhan produk harus direview sebelum deployment komersial.

## 6. Urutan implementasi

1. Audit ini dan baseline verifikasi aktual.
2. Konfigurasi prospektif generasi/batas anggaran/bonus nonaktif, integritas ancestry dan endpoint genealogy berukuran terbatas.
3. Histori rank, override sampai dicabut, maintenance, periode dan volume ledger beserta rekonsiliasi.
4. Calculator komisi bersama untuk engine/simulator, reversal/chargeback dan regression keuangan.
5. Dashboard produk dan admin dengan query riil; jangan ubah harga/rate existing saat redesign.
6. Integrasikan lazy genealogy, detail, filter dan analitik tanpa seluruh graph dalam satu response.
7. Dashboard komisi dan rekening/payout workflow yang tetap memerlukan transfer riil terkonfirmasi.
8. Marketing, attribution events, konversi, materi, kampanye dan insentif opsional nonaktif.
9. Portal mitra, pelanggan, Bahasa Indonesia dan akses data lintas akun.
10. Security/performance, tes MySQL concurrency, UAT staging dan keputusan readiness terpisah dari penyelesaian visual.

## 7. Verifikasi

Hasil build/tes sebelum sprint V2 berasal dari pengerjaan sebelumnya dan tidak otomatis dianggap bukti perubahan V2. Hasil aktual setiap sprint dicatat setelah command selesai. Status penerimaan seluruh V2: **belum selesai**.

### Increment fondasi yang sudah diterapkan

- Transfer manual dipertahankan sesuai keputusan pengguna; tidak menambahkan gateway pembayaran.
- Migration aditif `mlm_program_versions` mengarsipkan konfigurasi saat upgrade dan setiap perubahan berikutnya. Versi unik, trigger menolak UPDATE/DELETE, update pengaturan dan arsip berada dalam transaksi yang sama. Tidak merekonstruksi versi lama yang sebelumnya tidak diarsipkan; snapshot order existing tetap dipertahankan.
- Migration aditif `partner_rank_overrides` menyimpan satu override terkini per mitra. Keputusan dan pencabutannya dicatat pada audit log existing. Override bertahan lintas bulan sampai dicabut, alasan wajib, dan pencabutan memulihkan evaluasi omzet. Tidak mengubah saldo atau membuat komisi.
- Tindakan UI tersedia melalui Jaringan MLM → Atur peringkat pada daftar/detail node. Membutuhkan kedua izin `partners.manage` dan `commission-rules.manage`, diperiksa kembali oleh server. Evaluasi rank memakai lock mitra agar tidak menimpa override secara bersamaan.
- Kedua migration sudah dijalankan pada database lokal tanpa menghapus data. Rollback berisi riwayat diblokir di luar unit tests; gunakan migration forward untuk koreksi.
- Histori keputusan rank otomatis append-only, volume ledger, maintenance/periode konfigurabel dan lazy genealogy **masih belum diterapkan**. Override manual tidak menggantikan kebutuhan modul tersebut.

### Hasil verifikasi increment

- PHPUnit seluruh suite SQLite: 374 tes, 351 lulus, 23 dilewati, 0 gagal. Tes yang memerlukan MySQL/concurrency belum dibuktikan dengan hasil SQLite ini.
- Tes fokus konfigurasi dan override: 17 lulus, 82 assertion.
- PHPStan: 0 error; Pint: lulus.
- Build produksi frontend: berhasil. Pemeriksaan TypeScript awal menemukan route helper tanpa `.form` sesudah generate manual; build meregenerasi helper lengkap, kemudian typecheck diulang.
- Typecheck ulang: lulus; lint/format frontend: lulus; 12 tes UI Node: lulus. Perubahan terakhir pada pesan validasi diuji ulang: 4 tes override lulus.
- Kesamaan piksel terhadap gambar belum diverifikasi melalui browser; tidak dinyatakan selesai.
