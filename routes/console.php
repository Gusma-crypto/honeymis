<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('honey:expire-orders')->everyMinute()->withoutOverlapping();

Schedule::command('honey:process-finance')->everyMinute()->withoutOverlapping();
Schedule::command('honey:reconcile-finance')->daily()->withoutOverlapping();
