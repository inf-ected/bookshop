<?php

declare(strict_types=1);

use App\Features\Cart\Commands\CleanupCartsCommand;
use App\Features\Checkout\Commands\ExpirePendingOrdersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CleanupCartsCommand::class)->daily();
Schedule::command(ExpirePendingOrdersCommand::class)->everyFifteenMinutes();

Schedule::command('backup:run --only-db')->dailyAt('03:00');
Schedule::command('backup:clean')->dailyAt('02:00');
