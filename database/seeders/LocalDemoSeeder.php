<?php

namespace Database\Seeders;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commissions\Actions\ManageCommissionRule;
use App\Domains\Commissions\Models\CommissionRule;
use App\Domains\Commissions\Services\CommissionEngine;
use App\Domains\Orders\Actions\ManageOrder;
use App\Domains\Orders\Actions\PlaceOrder;
use App\Domains\Orders\Actions\SubmitPaymentProof;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\CartManager;
use App\Domains\Orders\Services\CheckoutQuote;
use App\Domains\Partners\Models\Partner;
use App\Domains\Partners\Services\ReferralCapture;
use App\Domains\Shared\Enums\SystemRole;
use App\Domains\Shared\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureLocalTarget();

        $password = config('commerce.demo_password');
        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException('Set DEMO_PASSWORD in the local Docker environment before seeding demo accounts.');
        }

        $this->call(RolePermissionSeeder::class);
        $this->seedUser('Demo Super Admin', 'admin@honey-demo.test', SystemRole::SuperAdmin, $password);
        $finance = $this->seedUser('Demo Finance', 'finance@honey-demo.test', SystemRole::Finance, $password);
        $partnerUser = $this->seedUser('Demo Mitra', 'partner@honey-demo.test', SystemRole::Partner, $password);
        $buyer = $this->seedUser('Demo Pembeli', 'customer@honey-demo.test', SystemRole::Customer, $password);

        $partner = Partner::updateOrCreate(
            ['user_id' => $partnerUser->id],
            ['referral_code' => 'DEMO-PARTNER', 'status' => 'active'],
        );

        $this->call(HoneyDemoCatalogSeeder::class);
        $this->seedCommissionRule($finance);
        $this->seedOrder($buyer, $partner, $finance, 'DEMO-MADU-HUTAN-250', 'local-demo-order:paid:v1', true);
        $this->seedOrder($buyer, $partner, $finance, 'DEMO-MADU-RANDU-250', 'local-demo-order:cancelled:v1', false);

    }

    private function ensureLocalTarget(): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $connectionName = (string) config('database.default');
        $connection = (array) config('database.connections.'.$connectionName, []);
        $database = (string) ($connection['database'] ?? '');
        $databaseHost = $connection['host'] ?? null;
        $isLocalDatabase = match ($connectionName) {
            'mysql', 'mariadb' => in_array($databaseHost, ['127.0.0.1', 'localhost', '::1', 'mysql'], true),
            'sqlite' => $database === ':memory:' && app()->runningUnitTests(),
            default => false,
        };

        if (! app()->environment(['local', 'testing']) || ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || ! $isLocalDatabase) {
            throw new RuntimeException('Demo accounts and transactions may only be seeded into a local Docker or test database.');
        }
    }

    private function seedUser(string $name, string $email, SystemRole $role, string $password): User
    {
        $user = User::where('email', $email)->first();
        if ($user === null) {
            $user = new User;
            $user->forceFill(['password' => $password]);
        }
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'status' => UserStatus::Active,
        ])->save();
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function seedOrder(User $buyer, Partner $partner, User $finance, string $variantSku, string $orderKey, bool $verifyPayment): void
    {
        $existingOrder = Order::where('idempotency_key', $orderKey)->first();
        if ($existingOrder !== null) {
            if ($existingOrder->status !== 'cancelled') {
                app(CommissionEngine::class)->accrue($existingOrder->id);
            }

            return;
        }

        $variant = ProductVariant::where('sku', $variantSku)->firstOrFail();
        $referralToken = app(ReferralCapture::class)->capture($partner->referral_code, null, $buyer->id);
        if ($referralToken === null) {
            throw new RuntimeException('Could not create referral attribution for the local demo order.');
        }

        $carts = app(CartManager::class);
        $carts->setQuantity($buyer, $variant->id, 1);
        $cart = $carts->current($buyer);
        $quote = app(CheckoutQuote::class)->calculate($cart, true);
        $order = app(PlaceOrder::class)->handle($buyer, [
            'recipient' => 'Penerima Demo',
            'phone' => '081234567890',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'district' => 'Coblong',
            'postal_code' => '40132',
            'address' => 'Alamat simulasi lokal; jangan kirim barang.',
        ], $quote['fingerprint'], $orderKey, $referralToken);

        if (! $verifyPayment) {
            app(ManageOrder::class)->handle($buyer, $order, 'cancel', $orderKey.':cancel');

            return;
        }

        $this->submitDemoProof($buyer, $order);
        app(ManageOrder::class)->handle($finance, $order, 'verify', $orderKey.':verify');
        app(CommissionEngine::class)->accrue($order->id);
    }

    private function seedCommissionRule(User $finance): void
    {
        $effectiveRule = CommissionRule::where('status', 'published')
            ->whereNull('product_id')
            ->whereNull('category_id')
            ->where('valid_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->first();
        if ($effectiveRule !== null) {
            return;
        }

        $rules = app(ManageCommissionRule::class);
        $draft = $rules->create($finance, [
            'version' => ((int) CommissionRule::max('version')) + 1,
            'product_id' => null,
            'category_id' => null,
            'rate_bps' => 1500,
            'fixed_amount' => 0,
            'cap_amount' => 100_000_000,
            'valid_from' => now()->subMinute(),
            'valid_until' => null,
        ]);
        $rules->publish($finance, $draft);
    }

    private function submitDemoProof(User $buyer, Order $order): void
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'honey-demo-proof-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Could not create a temporary demo payment proof.');
        }

        try {
            if (file_put_contents($temporaryPath, $this->paymentProofPdf($order->order_number)) === false) {
                throw new RuntimeException('Could not write the temporary demo payment proof.');
            }
            $file = new UploadedFile($temporaryPath, 'demo-payment-proof.pdf', 'application/pdf', UPLOAD_ERR_OK, true);
            app(SubmitPaymentProof::class)->handle($buyer, $order, $file, 'local-demo-proof:'.$order->id);
        } finally {
            if (is_file($temporaryPath) && ! unlink($temporaryPath)) {
                throw new RuntimeException('Could not remove the temporary demo payment proof.');
            }
        }
    }

    private function paymentProofPdf(string $orderNumber): string
    {
        $stream = 'BT /F1 22 Tf 72 700 Td (DEMO - BUKAN BUKTI TRANSFER RIIL) Tj 0 -45 Td /F1 14 Tf (Order '.$orderNumber.') Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }
}
