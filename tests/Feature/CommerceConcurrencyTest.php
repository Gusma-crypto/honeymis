<?php

namespace Tests\Feature;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Services\CartManager;
use App\Domains\Orders\Services\CheckoutQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CommerceConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_mysql_checkouts_cannot_reserve_the_same_last_unit(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('A real MySQL/InnoDB connection is required to exercise row locks across processes.');
        }
        $variant = ProductVariant::factory()->withStock(1)->create();
        $buyers = User::factory()->count(2)->create();
        $workers = [];
        $address = ['recipient' => 'Test', 'phone' => '08123', 'province' => 'Test', 'city' => 'Test', 'district' => 'Test', 'postal_code' => '12345', 'address' => 'Test address'];
        $connection = config('database.connections.'.DB::getDefaultConnection());
        $barrier = storage_path('framework/testing/checkout-'.Str::uuid());
        if (! is_dir(dirname($barrier))) {
            mkdir(dirname($barrier), 0777, true);
        }
        $workerCode = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$arguments = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config(['database.default' => 'mysql', 'database.connections.mysql' => $arguments['connection'], 'cache.default' => 'array']);
Illuminate\Support\Facades\DB::purge('mysql');
file_put_contents($arguments['barrier'].'.ready-'.$arguments['user'], 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($arguments['barrier'].'.go')) {
    if (microtime(true) > $deadline) { throw new RuntimeException('Checkout barrier timed out.'); }
    usleep(10000);
    clearstatcache();
}
try {
    $user = App\Models\User::findOrFail($arguments['user']);
    app(App\Domains\Orders\Actions\PlaceOrder::class)->handle($user, $arguments['address'], $arguments['quote'], $arguments['key']);
    echo 'placed';
} catch (Illuminate\Validation\ValidationException $exception) {
    echo 'rejected';
}
PHP;
        try {
            foreach ($buyers as $buyer) {
                app(CartManager::class)->setQuantity($buyer, $variant->id, 1);
                $quote = app(CheckoutQuote::class)->calculate(app(CartManager::class)->current($buyer));
                $worker = new Process([PHP_BINARY, '-r', $workerCode], base_path(), timeout: 25);
                $worker->setInput(json_encode([
                    'connection' => $connection, 'user' => $buyer->id, 'quote' => $quote['fingerprint'], 'address' => $address,
                    'key' => (string) Str::uuid(), 'barrier' => $barrier,
                ], JSON_THROW_ON_ERROR));
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            do {
                clearstatcache();
                $waiting = count(glob($barrier.'.ready-*') ?: []);
                if ((int) $waiting >= 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, (int) $waiting, 'Both checkout workers must reach the barrier before release.');
        } finally {
            touch($barrier.'.go');
        }
        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $results[] = trim($worker->getOutput());
        }
        foreach (glob($barrier.'.*') ?: [] as $barrierFile) {
            unlink($barrierFile);
        }
        sort($results);

        $this->assertSame(['placed', 'rejected'], $results);
        $this->assertSame(1, Order::count());
        $this->assertDatabaseHas('inventory_balances', ['variant_id' => $variant->id, 'on_hand' => 1, 'reserved' => 1]);
        $this->assertDatabaseCount('inventory_movements', 1);
    }
}
