<?php

namespace Tests\Feature;

use App\Domains\Wallet\Services\PartnerLedger;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\FinanceTestCase;

class FinanceConcurrencyTest extends FinanceTestCase
{
    use DatabaseMigrations;

    public function test_concurrent_payout_requests_cannot_reserve_the_same_available_funds(): void
    {
        $this->financeActors();
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('A real MySQL/InnoDB connection is required to exercise row locks across processes.');
        }
        $this->commissionOrder(true);
        $workers = [];
        $connection = config('database.connections.'.DB::getDefaultConnection());
        $barrier = storage_path('framework/testing/payout-'.Str::uuid());
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
file_put_contents($arguments['barrier'].'.ready-'.$arguments['worker'], 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($arguments['barrier'].'.go')) {
    if (microtime(true) > $deadline) { throw new RuntimeException('Payout barrier timed out.'); }
    usleep(10000);
    clearstatcache();
}
try {
    $partner = App\Domains\Partners\Models\Partner::findOrFail($arguments['partner']);
    config(['partners.minimum_payout_idr' => 1000]);
    app(App\Domains\Payouts\Actions\ManagePayout::class)->request($partner->user, $partner, 8000, ['bank_name'=>'Test','account_name'=>'Test','account_number'=>'1234567890'], $arguments['key']);
    echo 'placed';
} catch (Illuminate\Validation\ValidationException $exception) {
    echo 'rejected';
}
PHP;
        try {
            foreach ([1, 2] as $workerId) {
                $worker = new Process([PHP_BINARY, '-r', $workerCode], base_path(), timeout: 25);
                $worker->setInput(json_encode([
                    'connection' => $connection, 'worker' => $workerId, 'partner' => $this->partner->id,
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
            $this->assertSame(2, (int) $waiting, 'Both payout workers must reach the barrier before release.');
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
        $this->assertDatabaseCount('payout_requests', 1);
        $balances = app(PartnerLedger::class)->balances($this->partner->id);
        $this->assertSame(8000, $balances['reserved']);
        $this->assertSame(2000, $balances['available']);
        $this->artisan('honey:reconcile-finance')->assertSuccessful();
    }

    public function refreshDatabase(): void
    {
        // DatabaseMigrations owns isolation so child processes can see committed fixtures.
    }
}
