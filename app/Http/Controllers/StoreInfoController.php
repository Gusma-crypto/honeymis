<?php

namespace App\Http\Controllers;

use App\Domains\Orders\Models\ShippingSetting;
use App\Domains\Orders\Services\ShippingSettings;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StoreInfoController extends Controller
{
    public function show(string $page): Response
    {
        $content = [
            'about' => ['Tentang HoneyCommerce', 'HoneyCommerce adalah toko produk madu dengan program mitra berbasis penjualan produk. Tidak ada komisi dari pendaftaran atau perekrutan. Informasi produk dan klaim mengikuti deskripsi masing-masing produk.'],
            'contact' => ['Hubungi toko', config('commerce.contact') ?: 'Kontak resmi belum dikonfigurasi. Silakan hubungi pengelola sebelum melakukan pembayaran.'],
            'faq' => ['Pertanyaan umum', 'Pesanan dibuat melalui keranjang dan checkout setelah login. Pembayaran menggunakan transfer manual ke rekening yang dikonfirmasi pengelola. Unggah bukti pada halaman pesanan, lalu tunggu verifikasi Finance. Nomor resi tampil setelah pesanan dikirim.'],
            'privacy' => ['Privasi', 'Akun, alamat pengiriman dan data pesanan digunakan untuk pemenuhan pesanan. Bukti pembayaran dan rekening payout dibatasi untuk pemilik dan petugas berwenang. Program referral memakai cookie untuk atribusi maksimal '.config('partners.referral_days').' hari. Hubungi pengelola untuk permintaan akses atau koreksi data. Kebijakan final, retensi dan identitas pengendali data harus disetujui pengelola sebelum peluncuran.'],
            'terms' => ['Ketentuan layanan', 'Harga dan ongkir final ditampilkan saat checkout. Pembayaran harus diverifikasi sebelum pengiriman. Komisi mitra hanya berasal dari penjualan eligible satu level berdasarkan versi aturan saat checkout; pendaftaran tidak mendapat bonus. Tarif dan batas payout ditampilkan di portal mitra. Ketentuan bisnis final memerlukan persetujuan pengelola.'],
            'returns' => ['Retur dan refund', 'Ajukan refund per item atau ongkir melalui halaman pesanan dengan alasan. Pengelola meninjau permintaan dan mengembalikan dana secara manual. Refund mengurangi komisi terkait; jika sudah dibayar, penyesuaian menjadi piutang terhadap penghasilan berikutnya. Masa tunggu pencairan komisi saat ini '.config('partners.return_window_days').' hari setelah pesanan diterima. Syarat kelayakan retur final harus dikonfirmasi pengelola.'],
        ];
        abort_unless(isset($content[$page]), 404);

        return Inertia::render('shop/info', ['title' => $content[$page][0], 'body' => $content[$page][1]]);
    }

    public function settings(ShippingSettings $shipping): Response
    {
        abort_unless(Gate::allows('catalog.manage') || Gate::allows('commission-rules.manage'), 403);

        return Inertia::render('admin/commerce-settings', ['shippingSettings' => $shipping->present(), 'canManageShipping' => Gate::allows('manage', ShippingSetting::class), 'settings' => ['Mata uang' => 'IDR (integer)', 'Pembayaran' => 'Transfer manual', 'Tarif ongkir manual IDR' => $shipping->current()->manual_amount, 'Masa retur komisi (hari)' => config('partners.return_window_days'), 'Minimum payout IDR' => config('partners.minimum_payout_idr'), 'Budget komisi bps' => config('partners.commission_budget_bps'), 'TTL referral (hari)' => config('partners.referral_days'), 'Instruksi pembayaran' => config('commerce.manual_payment_instructions'), 'Kontak' => config('commerce.contact') ?: 'Belum diatur']]);
    }
}
