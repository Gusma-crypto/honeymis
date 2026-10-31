<?php

namespace App\Console\Commands;

use App\Domains\Orders\Actions\ManageOrder;
use App\Domains\Orders\Models\Order;
use Illuminate\Console\Command;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'honey:expire-orders';

    protected $description = 'Release expired unpaid orders; submitted proof waits for finance review';

    public function handle(ManageOrder $orders): int
    {
        $count = 0;
        foreach (Order::where('status', 'pending_payment')->where('expires_at', '<=', now())->orderBy('id')->lazyById() as $order) {
            $count += $orders->expire($order) ? 1 : 0;
        }
        $this->info($count.' expired orders released.');

        return self::SUCCESS;
    }
}
