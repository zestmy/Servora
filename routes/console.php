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

// Monitor ingredient price changes daily at 7 AM MYT
Schedule::command('price:monitor')->dailyAt('07:00');

// Send scheduled analytics reports every 15 minutes
// (checks for reports due based on their delivery_time setting)
Schedule::command('reports:send-scheduled')->everyFifteenMinutes();

// Find out what actually happened to PrintNode label jobs. Every ten
// minutes because a chef asking "did that print?" wants an answer within
// the shift, not tomorrow. withoutOverlapping because a slow PrintNode
// response must not stack runs on top of each other.
Schedule::command('labels:reconcile-jobs')->everyTenMinutes()->withoutOverlapping();

// Clear out rendered SOP export PDFs (~12 MB each) past their retention
// window, and fail off any run whose worker died without reporting. Hourly
// rather than daily because the second half of that is what unsticks the
// Training Portal's export button after a deploy restarts the queue worker
// mid-render.
Schedule::command('sop:prune-exports')->hourly()->withoutOverlapping();
