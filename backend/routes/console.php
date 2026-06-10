<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('supercarnes:expire-coupons')->everyMinute();
Schedule::command('livescore:sync-live')->everyMinute();
Schedule::command('livescore:sync-commentary')->everyMinute();
Schedule::command('livescore:sync-fixtures')->hourly();
Schedule::command('contest:reverify-invoices')->weekly();
Schedule::command('contest:expire-unresponsive-winners')->everyMinute();
Schedule::command('push-campaigns:dispatch-scheduled')->everyMinute();
