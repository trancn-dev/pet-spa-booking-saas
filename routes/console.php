<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Plan lifecycle (task 4.5): 00:05 in Viet Nam (UTC+7), every shop.
Schedule::command('tenants:check-expiry')->dailyAt('17:05')->withoutOverlapping()->onOneServer();
