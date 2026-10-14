<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('listings:expire')->hourly();
Schedule::command('listings:maintain --freshness')->hourly();
Schedule::command('listings:maintain --counters')->everyFiveMinutes();
Schedule::command('engagement:run --visits')->everyTenMinutes();
Schedule::command('engagement:run --unanswered')->hourly();
Schedule::command('engagement:run --favorites')->everyFifteenMinutes();
Schedule::command('engagement:run --alerts')->everyFiveMinutes();
Schedule::command('commercial:run --expire')->everyTenMinutes();
Schedule::command('commercial:run --remind')->hourly();
Schedule::command('commercial:run --enforce')->hourly();
