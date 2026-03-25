<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process recurring billing daily at 6 AM MYT
Schedule::command('billing:process-recurring')->dailyAt('06:00');

// Snapshot usage daily at midnight
Schedule::command('usage:snapshot')->dailyAt('00:00');

// Send onboarding emails daily at 9 AM MYT
Schedule::command('onboarding:send-emails')->dailyAt('09:00');
