# Architecture Decision Records - Phase 0

Tanggal: 26 Oktober 2026. Status keputusan teknis: proposed for Phase 0 approval, sudah direalisasikan pada fondasi jika ditandai implemented. Kebijakan bisnis belum merupakan sign-off.

## ADR-001 - Pertahankan starter kit, modular monolith

Implemented. Pertahankan Laravel 13/PHP 8.4 dan React + TypeScript + Inertia 3 + Tailwind 4 yang ada. Domain di `app/Domains`, PSR-4 `App` yang sudah tersedia; satu PostgreSQL, Redis queue/cache. Tidak membuat microservices atau API frontend terpisah. Konsekuensi: komunikasi lintas modul melalui actions/contracts dan outbox; reports read-only terhadap sumber domain.

## ADR-002 - Additive migrations dan PostgreSQL sebagai target kebenaran

Implemented. Tidak menjalankan migrasi pada MySQL aktif. Tabel baru dibuat terpisah dari auth yang sudah ada. CHECK/partial index/trigger khusus PostgreSQL; schema dasar masih bisa dijalankan SQLite untuk smoke test, tetapi SQLite bukan bukti invariant produksi. Down migration berurutan reverse dependency. Rollback yang menghapus tabel adalah destruktif terhadap data; produksi harus forward-fix dan backup.

## ADR-003 - Integer IDR, basis points, pembulatan deterministik

Implemented untuk value object/calculator. BIGINT untuk nominal dan int rate_bps, floor ke rupiah utuh. Net merchandise = harga baris - diskon baris; ongkir/pajak tidak memberi komisi. Quantity, net dan cap membatasi payout komisi. Cap per item/entitlement adalah usulan yang harus dikonfirmasi finance untuk menghindari abuse split item; order-level budget enforcement akan ditambahkan sebelum publish rule. Refund parsial menghitung delta komisi dari cumulative net setelah refund, bukan membulatkan setiap refund secara independen.

## ADR-004 - Direct satu level dan rule version immutable

Implemented. `CommissionRule` memungkinkan strategi lain nanti, tetapi `RuleVersion` dan database menolak selain direct_v1/level=1. Version unique; published rule tidak bisa diubah menjadi draft atau diedit isinya. Rule baru memakai version baru. Rate dan fixed_amount bersifat mutually exclusive; cap wajib dan nonnegatif, cap nol menghasilkan komisi nol. Tidak ada listener pendaftaran yang membuat komisi. Publish, approval, simulation budget dan snapshot checkout adalah Phase 3 (snapshot checkout dimulai Phase 2).

## ADR-005 - Ledger append-only dan serialisasi writer

Schema dan balance calculator implemented; posting workflows Phase 3/4. Saldo per partner/bucket adalah agregasi signed entries. History tidak di-update/delete, reversal adalah baris baru. Lock partner row untuk semua writer wallet, urutan lock konsisten, reserve sebelum payout, unique idempotency_key per leg. FK komposit mencegah ledger terikat ke komisi/payout milik partner lain. DB runtime role tidak diberi hak TRUNCATE/DDL; trigger bukan perlindungan terhadap database superuser.

## ADR-006 - Payment manual dan kontrak adapter

Implemented kontrak minimal provider + initialStatus. Manual payment selalu pending, tidak auto-verify. Upload proof menghasilkan submitted; Finance memverifikasi nominal/order/proof lalu verified. Unique partial index mencegah dua pembayaran verified/refunded untuk order sama. Pembayaran ditolak dapat diajukan sebagai attempt baru. Kontrak gateway init/status/webhook akan diperluas ketika provider dipilih; saat ini tidak ada HTTP gateway, webhook route atau transfer bank otomatis.

## ADR-007 - State graph dipisahkan dari business guards

Implemented enum graphs dan TransitionRecorder. Graph hanya menentukan transisi sah. Authorization, paid eligibility, delivered + return window, atomic ledger movements, proof payout dan refund limits diperiksa dalam domain action sebelum record pada transaksi yang sama. Refund parsial tidak mengubah status order menjadi refunded; status itu hanya untuk full refund. Primitive tidak dijadikan endpoint.

## ADR-008 - Atomic audit/outbox dan idempotency database

Implemented primitive. Lock aggregate, tulis status + audit + outbox atomik; replay key sama tidak menggandakan event. Retry key dengan payload/actor berbeda ditolak. Consumer dedupe memakai event_key dan unique financial entitlement. Untuk write lain Phase 2-4, simpan fingerprint canonical payload di idempotency record/domain row agar replay request yang diubah ditolak. Redis locks/unique jobs hanya optimasi, bukan pengganti transaksi DB.

## ADR-009 - RBAC Spatie dan ownership Policies

Planned Phase 1, belum dipasang. Tidak mengganti auth Fortify atau menyalin schema Spatie secara manual. Installer Composer memilih stable version yang kompatibel Laravel 13/PHP 8.4; tidak menggunakan main/dev sebagai dependency. Publish config/migration vendor, register permissions granular dan policy mapping eksplisit untuk domain models. Tidak ada endpoint domain yang dapat diakses sebelum policy/validation/test selesai.

## ADR-010 - Atribusi dan immutable sponsor

Schema + eligibility predicate implemented; atribusi cookie/session/action Phase 3. Usulan first-valid-referral TTL 30 hari dan sponsor immutable setelah approved_at. Snapshot order menyimpan rule dan direct beneficiary sehingga perubahan rule tidak retrospektif. Self-referral ditolak; suspended/rejected partner tidak eligible. Cookie berupa signed opaque token; database hanya menyimpan token_hash. Guest checkout sementara tidak diaktifkan sampai strategi anti self-referral disepakati.

## Keputusan bisnis menunggu pemilik/finance

1. Rate/cap per produk, prioritas product > category > global, maksimum budget order, dan pembulatan diskon.
2. Masa retur dan status release; pencairan hanya setelah delivered + masa retur + tidak fraud/refund terbuka.
3. Minimum/jadwal payout, rekening penerima, bukti dan maker-checker.
4. Perlakuan receivable refund pascapayout; jangan menghapus payout historis.
5. Guest checkout, approval mitra, self-purchase dan referral TTL.
6. Semua multi-level memerlukan sign-off terpisah; contoh PRD bukan konfigurasi aktif.

## ADR-011 - MySQL/InnoDB menggantikan target PostgreSQL

Accepted dan implemented pada Phase 2 berdasarkan instruksi eksplisit pengguna. ADR-001/002 dan rencana runtime PostgreSQL pada dokumen fase sebelumnya disupersede khusus pilihan database. Gunakan driver mysql, InnoDB dan strict mode; template .env.example serta config fallback disesuaikan. Lokal diuji pada MariaDB 10.4.32; minimum MySQL 8.0.16 atau MariaDB 10.4 agar CHECK benar-benar ditegakkan.

Tambahkan migration baru, jangan mengubah migration historis PostgreSQL yang sudah dicatat. CHECK melindungi stok/uang/status/direct level. Generated verified_order_id yang nullable dan unique menggantikan partial index untuk satu verified/refunded payment per order. Trigger SIGNAL melindungi immutable audit/ledger, versi rule published dan sponsor. FK komposit mengikat ledger ke pemilik commission/payout yang sama. Self-parent/sponsor memakai trigger karena CHECK MySQL tidak boleh merujuk kolom AUTO_INCREMENT.

DDL MySQL melakukan implicit commit. Migration memeriksa invariant existing rows terlebih dahulu, tidak memperbaiki/menghapus histori secara otomatis, dan dapat melanjutkan operasi yang sudah ada jika percobaan awal terhenti. Rollback diuji hanya pada database test. Semua transaksi stok memakai locking read aktual, bukan snapshot consistent-read lama; test dua worker membuktikan satu unit terakhir tidak terjual dua kali.

Referensi: [MySQL CHECK](https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html), [MariaDB constraints](https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint).

## ADR ? Alokasi payout dan reversal setelah settlement

Keputusan implementasi MVP: setiap request payout mereservasi saldo dan membuat payout_allocations yang menunjuk entitlement komisi pemilik yang sama. FK komposit mencegah pemakaian komisi milik mitra lain. Payout boleh mengambil sebagian hak tarik; sum alokasi harus sama dengan nominal request.

Refund membatalkan seluruh payout terbuka yang mengandung item terdampak sebelum reversal. Payout yang sudah paid tetap historis; kekurangan dana menjadi receivable dan dipotong dari komisi available berikutnya. Tidak mengubah atau menghapus ledger lama. Saldo tersedia tidak boleh negatif. Rekonsiliasi memakai identitas liability dikurangi receivable, bukan menganggap receivable sebagai pendapatan.

Konsekuensi: refund sebagian dapat membatalkan satu request payout yang berisi beberapa komisi; mitra menerima notifikasi dan dapat mengajukan lagi atas sisa saldo. Stok barang retur yang sudah dikirim perlu inspeksi dan penyesuaian inventory manual yang diaudit.

## ADR ? Tanggal bisnis MySQL/MariaDB eksplisit

Pengujian mendeteksi implicit DEFAULT/ON UPDATE pada kolom TIMESTAMP bisnis pertama di MariaDB lokal. Migration finance mengubah valid_from, placed_at, requested_at, referral/cart expires_at dan outbox available_at ke DATETIME NOT NULL tanpa perubahan nilai. Waktu aplikasi tetap UTC, filter laporan memakai batas hari Asia/Jakarta. Tipe yang aman ini tidak dikembalikan ke implicit TIMESTAMP oleh down migration.
