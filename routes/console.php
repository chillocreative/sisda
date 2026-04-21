<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run heuristic + Claude scan every 15 minutes. Requires a cron entry
// on the server: `* * * * * cd /path && php artisan schedule:run`.
Schedule::call(function () {
    app(\App\Services\UserLogAlertService::class)->analyzeAndAlert();
})
    ->name('user-log.analyze-and-alert')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
