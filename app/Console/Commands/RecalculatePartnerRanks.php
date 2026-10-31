<?php

namespace App\Console\Commands;

use App\Domains\Partners\Services\PartnerRankService;
use Illuminate\Console\Command;

class RecalculatePartnerRanks extends Command
{
    protected $signature = 'honey:recalculate-ranks';

    protected $description = 'Recalculate partner ranks from valid product sales for the current Jakarta month';

    public function handle(PartnerRankService $ranks): int
    {
        $ranks->recalculateCurrentMonth();
        $this->info('Monthly partner ranks recalculated.');

        return self::SUCCESS;
    }
}
