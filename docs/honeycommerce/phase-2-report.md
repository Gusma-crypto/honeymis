# Phase 2 - commerce selesai dengan MySQL

Tanggal: 26 Oktober 2026 (Asia/Jakarta).
Status: implementasi dan acceptance Phase 2 selesai; tidak memulai Phase 3 atau deploy produksi.

## Keputusan database terbaru

Sesuai instruksi pengguna, gunakan driver mysql dan InnoDB. Koneksi .env tetap mysql ke database pengembangan db_mlm. Server yang tersedia lokal adalah MariaDB 10.4.32 (keluarga MySQL), bukan binary Oracle MySQL 8. Seluruh verifikasi akhir memakai server ini; tidak mengklaim telah menguji binary MySQL 8 terpisah. Minimum yang dijaga migration: MySQL 8.0.16 atau MariaDB 10.4, karena versi lama dapat mengabaikan CHECK.

Template .env.example dan fallback database.default kini MySQL; tidak mengganti password, nama database atau kredensial .env aktif. PostgreSQL sementara yang sempat dipakai sebelum koreksi pengguna sudah dihentikan. Migration PostgreSQL historis tetap ada agar riwayat schema tidak rusak, tetapi dilewati pada driver mysql. Tidak ada PostgreSQL yang diperlukan untuk Phase 2.

## Fitur yang selesai

- Beranda menjadi katalog HoneyCommerce, dengan pencarian nama, filter kategori, pagination, detail produk, varian, foto dan stok tersedia. Produk/kategori/varian inactive tidak dapat dibeli. Harga publik tidak mengekspos cost.
- Pengelolaan kategori dan produk/varian oleh Admin Toko/Super Admin. Harga, cost dan diskon nominal per unit berupa integer IDR. Produk baru mulai stok nol. Foto JPG/PNG/WebP divalidasi; histori produk dijaga melalui status inactive, bukan delete.
- Keranjang persisten milik akun; jumlah dapat diubah/hapus. Checkout memerlukan login dan verified email; guest checkout belum diaktifkan sesuai keputusan fase sebelumnya.
- Quote dihitung server dari harga/diskon aktif dan ongkir flat. Browser tidak dapat menetapkan total/diskon/referral. Quote fingerprint menolak perubahan harga/isi keranjang sebelum checkout.
- Checkout transactional dan idempotent: order_number unique, fingerprint payload, snapshot alamat/harga/diskon/cost/eligibility, reserve stok, payment manual pending, audit dan pengosongan cart atomik. Retry identik mengembalikan order sama; key dengan payload/owner lain ditolak.
- Locking read berurutan untuk produk, kategori, varian dan inventory. Objek dari locking read dipakai langsung sehingga REPEATABLE READ MySQL tidak mengembalikan snapshot stok lama. Uang dihitung dengan Idr integer dan overflow checks; diskon per unit dikalikan quantity secara tepat, tanpa float/rounding.
- Transfer manual memakai PaymentAdapter yang dibind ke ManualPaymentAdapter. Upload JPG/PNG/PDF maks. 5 MB disimpan privat dengan hash; hanya pemilik order atau Finance dapat mengunduh. Tidak ada upload bukti yang langsung menyatakan paid.
- Finance memverifikasi/menolak bukti submitted. Bukti ditolak dapat diajukan ulang sebagai payment attempt baru; query memilih attempt terbaru dengan reorder, bukan mencoba memverifikasi attempt lama. Verifikasi ulang key identik tidak menggandakan paid event.
- Admin Toko memproses paid order, memasukkan kurir/resi, menandai shipped dan delivered. Pengiriman mengurangi on_hand dan reserved sekaligus; paid/verified guards mencegah pengiriman order belum dibayar. Shipment dan delivered_at dicatat.
- Pemilik/Admin dapat membatalkan order pending_payment. Cancel/expiry melepas reservasi sekali dan menolak pembayaran berikutnya pada order cancelled. Submitted proof tidak otomatis kedaluwarsa selama menunggu Finance. Scheduler honey:expire-orders didaftarkan setiap menit dan withoutOverlapping; worker/scheduler tetap harus dijalankan operator.
- Policies, FormRequests, auth/verified, password confirmation untuk mutasi status/admin, throttle, CSRF, audit dan outbox state transition tetap server-side. Tidak ada bonus referral, ledger komisi atau payout yang diaktifkan pada Phase 2.

## Invariant MySQL tambahan

Migration add_mysql_commerce_invariants memasang CHECK untuk uang/stok/status/direct level, FK owner ledger, trigger ledger/audit append-only, rule published dan sponsor immutable, serta self-parent/sponsor guards. Generated nullable verified_order_id dengan unique index menggantikan partial index: satu order tidak dapat mempunyai dua payment verified/refunded.

Migration memeriksa existing rows sebelum DDL dan tidak memperbaiki histori secara diam-diam. MySQL DDL melakukan implicit commit; constraint/index/trigger yang sudah dibuat dikenali saat melanjutkan percobaan migrasi. Rollback hanya diuji pada database test. Trigger bukan pengganti pembatasan hak DDL/TRUNCATE akun database produksi.

## File ditambahkan

- `app/Console/Commands/ExpireUnpaidOrders.php`
- `app/Domains/Catalog/Actions/SaveProduct.php`
- `app/Domains/Catalog/Models/Category.php`
- `app/Domains/Catalog/Models/Product.php`
- `app/Domains/Catalog/Models/ProductVariant.php`
- `app/Domains/Inventory/Actions/AdjustInventory.php`
- `app/Domains/Inventory/Models/InventoryBalance.php`
- `app/Domains/Orders/Actions/ManageOrder.php`
- `app/Domains/Orders/Actions/PlaceOrder.php`
- `app/Domains/Orders/Actions/SubmitPaymentProof.php`
- `app/Domains/Orders/Models/Cart.php`
- `app/Domains/Orders/Models/CartItem.php`
- `app/Domains/Orders/Models/Customer.php`
- `app/Domains/Orders/Models/Order.php`
- `app/Domains/Orders/Models/OrderItem.php`
- `app/Domains/Orders/Models/Payment.php`
- `app/Domains/Orders/Models/Shipment.php`
- `app/Domains/Orders/Services/CartManager.php`
- `app/Domains/Orders/Services/CheckoutQuote.php`
- `app/Domains/Shared/Services/AuditWriter.php`
- `app/Http/Controllers/Admin/CatalogManagementController.php`
- `app/Http/Controllers/Admin/InventoryController.php`
- `app/Http/Controllers/Admin/OrderManagementController.php`
- `app/Http/Controllers/CartController.php`
- `app/Http/Controllers/CatalogController.php`
- `app/Http/Controllers/CheckoutController.php`
- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Requests/Admin/CategoryRequest.php`
- `app/Http/Requests/Admin/InventoryAdjustmentRequest.php`
- `app/Http/Requests/Admin/ProductRequest.php`
- `app/Http/Requests/Commerce/CartItemRequest.php`
- `app/Http/Requests/Commerce/CheckoutRequest.php`
- `app/Http/Requests/Commerce/OrderTransitionRequest.php`
- `app/Http/Requests/Commerce/PaymentProofRequest.php`
- `app/Policies/OrderPolicy.php`
- `app/Policies/PaymentPolicy.php`
- `app/Policies/ProductPolicy.php`
- `config/commerce.php`
- `database/factories/CartFactory.php`
- `database/factories/CartItemFactory.php`
- `database/factories/CategoryFactory.php`
- `database/factories/CustomerFactory.php`
- `database/factories/InventoryBalanceFactory.php`
- `database/factories/OrderFactory.php`
- `database/factories/OrderItemFactory.php`
- `database/factories/PaymentFactory.php`
- `database/factories/ProductFactory.php`
- `database/factories/ProductVariantFactory.php`
- `database/factories/ShipmentFactory.php`
- `database/migrations/2026_10_26_143015_add_checkout_controls_to_commerce_tables.php`
- `database/migrations/2026_10_26_144743_add_mysql_commerce_invariants.php`
- `database/seeders/CatalogDemoSeeder.php`
- `docs/honeycommerce/phase-2-report.md`
- `phpunit.mysql.xml`
- `resources/js/components/commerce-ui.tsx`
- `resources/js/pages/admin/catalog.tsx`
- `resources/js/pages/admin/inventory.tsx`
- `resources/js/pages/commerce/cart.tsx`
- `resources/js/pages/commerce/checkout.tsx`
- `resources/js/pages/commerce/order.tsx`
- `resources/js/pages/commerce/orders.tsx`
- `resources/js/pages/shop/catalog.tsx`
- `resources/js/pages/shop/product.tsx`
- `resources/js/types/commerce.ts`
- `tests/Feature/CatalogManagementTest.php`
- `tests/Feature/CommerceCheckoutTest.php`
- `tests/Feature/CommerceConcurrencyTest.php`
- `tests/Feature/CommerceOperationsTest.php`

## File diperbarui

- `.env.example`
- `config/database.php`
- `app/Providers/AppServiceProvider.php`
- `app/Providers/AuthorizationServiceProvider.php`
- `routes/web.php`
- `routes/console.php`
- `tests/TestCase.php`
- `tests/Feature/HoneyCommerceFoundationTest.php`
- `resources/js/app.tsx`
- `resources/js/components/app-sidebar.tsx`
- `docs/honeycommerce/architecture.md`
- `docs/honeycommerce/adrs.md`
- `docs/honeycommerce/erd.md`
- `docs/honeycommerce/acceptance.md`

Generated Wayfinder actions/routes dan Vite public/build juga dibangun ulang. Dependency PHP/JS tidak diubah pada Phase 2. PRD referensi, auth yang ada, kredensial .env dan laporan historis Phase 0/1 tidak ditimpa. Tidak ada repository Git; daftar di atas menjadi manifest perubahan.

## Command migrasi dan pengoperasian lokal

```powershell
php artisan migrate --no-interaction
php artisan migrate:status --no-interaction
php artisan route:list --except-vendor --no-interaction
php artisan wayfinder:generate --with-form --no-interaction
php artisan schedule:list --no-interaction
php artisan honey:expire-orders --no-interaction
npm run build
```

Migrate sudah diterapkan pada database pengembangan MySQL yang aktif; semua migration berstatus Ran. Tidak melakukan migrate:fresh/rollback terhadap database aktif.

Konfigurasi opsional di .env, gunakan nominal dan rekening resmi milik toko:

```dotenv
COMMERCE_SHIPPING_FLAT_IDR=20000
COMMERCE_PAYMENT_INSTRUCTIONS="Petunjuk transfer dan rekening resmi toko"
```

Default tidak berisi rekening fiktif. Belum ada akun dipromosikan otomatis. Gunakan akun Admin Toko untuk katalog/stok/order dan Finance untuk verifikasi; bootstrap Super Admin pertama tetap command terkontrol dari laporan Phase 1. CatalogDemoSeeder bersifat opsional, local-only, butuh admin yang sudah ada dan tidak dijalankan otomatis. Tidak membuat transaksi/uang riil sebagai demo.

Aplikasi lokal dapat dijalankan dengan php artisan serve --no-interaction dan npm run dev, serta scheduler dengan php artisan schedule:work --no-interaction. Foto dilayani route aplikasi, sehingga tidak memerlukan symlink storage untuk tampil. Bukti transfer selalu lewat route download yang diotorisasi.

## Test dan hasil

Database test baru honeycommerce_phase2_test dibuat terpisah pada server MySQL lokal yang sama. TestCase menolak MySQL test database yang tidak berakhiran _test sebelum lifecycle test/migrasi dimulai. Kredensial diwarisi dari lingkungan pengembangan dan tidak ditulis ke laporan/argumen worker. Worker concurrency menerima konfigurasi lewat stdin.

```powershell
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='honeycommerce_phase2_test'
php vendor/bin/phpunit -c phpunit.mysql.xml
```

Untuk mengulang, siapkan database test kosong berakhiran _test dengan akun test yang memiliki hak migration. Jangan arahkan PHPUnit/RefreshDatabase/DatabaseMigrations ke db_mlm atau database operasional.

```powershell
# Hanya pada database test yang boleh diubah/dihapus:
php artisan migrate:rollback --step=2 --no-interaction
php artisan migrate --no-interaction
php vendor/bin/phpunit -c phpunit.mysql.xml

php -d memory_limit=512M vendor/bin/phpstan analyse --no-progress --error-format=table
npm run types:check
npm run check -- resources/js/components/commerce-ui.tsx resources/js/types/commerce.ts resources/js/pages/shop resources/js/pages/commerce resources/js/pages/admin/catalog.tsx resources/js/pages/admin/inventory.tsx resources/js/components/app-sidebar.tsx resources/js/app.tsx
npm run build
```

| Pemeriksaan                                                            | Hasil                                                                                |
| ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Full PHPUnit pada driver MySQL/MariaDB                                 | 189 tests, 528 assertions, seluruhnya lulus, tanpa skip                              |
| Dua checkout process untuk stok terakhir                               | tepat satu placed, satu rejected, reserved = 1, satu movement                        |
| Rollback dua migration Phase 2 lalu migrate ulang pada database test   | berhasil                                                                             |
| CHECK, trigger immutable, generated unique payment dan FK owner ledger | test invariant seluruhnya lulus pada MySQL/MariaDB                                   |
| PHPStan level 7                                                        | 0 error                                                                              |
| TypeScript                                                             | lulus                                                                                |
| Format/lint 12 file frontend yang berubah                              | lulus tanpa warning/error                                                            |
| Vite production build                                                  | lulus; peringatan optional fontaine tidak menghalangi build                          |
| Pint scope PHP yang berubah                                            | dijalankan; --dirty tidak tersedia tanpa Git, sehingga memakai daftar file eksplisit |

Test mencakup invalid/inactive/out-of-stock cart, stale quote, snapshot totals, checkout retry/conflict, browser totals, ownership, proof MIME/private download, role restrictions, no unpaid shipping, rejected payment resubmission, cancellation/retry, expiry/manual review, category create, variant ownership, IDR validation, inventory reserve limits dan duplicate verification/shipment. Test concurrency memakai dua proses PHP sungguhan dengan file barrier, bukan mock row lock. Tidak mengklaim browser E2E/visual QA telah dijalankan.

## Risiko dan batas fase

1. Petunjuk rekening transfer harus diisi pemilik toko sebelum menerima pembelian riil. Tidak ada transfer bank otomatis; Finance tetap memverifikasi pembayaran di luar aplikasi.
2. Ongkir flat default Rp20.000 adalah konfigurasi development yang dapat diganti, bukan tarif kurir yang dijamin. Tax default nol; kebijakan pajak/kurir/promo global belum diimplementasikan.
3. Snapshot referral/rule masih null sampai Phase 3. commission_eligible tidak bisa diaktifkan lewat form katalog pada fase ini. Tidak ada komisi rekrutmen/multi-level.
4. Refund/return dan reversal komisi/payout adalah fase berikutnya. Order berakhir delivered pada UI Phase 2; completed dan release komisi menunggu aturan masa retur Phase 3. Tidak menyediakan tombol bypass refund atau payout.
5. Proof submitted menahan stok sampai Finance meninjau. Operator perlu menjalankan scheduler dan memantau order menunggu review; daftar belum memiliki filter operasional lanjutan.
6. Redis belum diaktifkan pada runtime lokal; session/cache/queue tetap driver database yang ada. Outbox tersimpan atomik, consumer komisi belum aktif sebelum Phase 3.
7. PostgreSQL lama hanya riwayat; target yang berlaku adalah MySQL. Binary Oracle MySQL 8 belum diuji terpisah, walau syntax migration dijaga kompatibel dengan minimum versi yang disebut. Deployment/backup/restore dan hardening produksi tetap Phase 5 dan butuh approval.
8. Seeder demo tidak dijalankan, sehingga katalog dapat kosong sampai admin memasukkan produk/foto/stok. Tidak memilih admin atau membuat password default tanpa identitas yang ditentukan pengguna.
9. Tidak ada perubahan production data, pengiriman uang, deployment produksi atau aktivasi Phase 3. Berhenti untuk review Phase 2 dan persetujuan fase berikutnya.

## Pemeriksaan global yang masih terhambat format dokumen

npm run check global masih gagal pada formatting tiga file yang sudah ada: PRD_HoneyCommerce_Laravel.md, docs/honeycommerce/phase-0-report.md dan docs/honeycommerce/state-machines.md. Isi referensi dan laporan historis tersebut dipertahankan; tidak ada klaim global check sudah hijau. Scope seluruh frontend yang berubah sudah lulus format/lint, dan TypeScript serta build lulus.
