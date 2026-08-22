<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process recurring billing daily at 6 AM MYT
Schedule::command('billing:process-recurring')->dailyAt('06:00');

// Ask CHIP-IN what happened to payments whose webhook never arrived. A lost
// callback leaves the customer paid and Servora showing a pending payment, no
// invoice and a subscription still on trial — and nothing used to close that
// loop, which is how one purchase sat pending from March to August. Hourly,
// because a customer who has paid should not wait a day to be activated.
Schedule::command('chipin:reconcile')->hourly()->withoutOverlapping();

// Snapshot usage daily at midnight
Schedule::command('usage:snapshot')->dailyAt('00:00');

// Send onboarding emails daily at 9 AM MYT
Schedule::command('onboarding:send-emails')->dailyAt('09:00');

// Monitor ingredient price changes daily at 7 AM MYT
Schedule::command('price:monitor')->dailyAt('07:00');

// Send scheduled analytics reports every 15 minutes
// (checks for reports due based on their delivery_time setting)
Schedule::command('reports:send-scheduled')->everyFifteenMinutes();

// Find out what actually happened to PrintNode label jobs, and expire
// agent jobs nobody collected. Every ten minutes because a chef asking
// "did that print?" wants an answer within the shift, not tomorrow.
// withoutOverlapping because a slow PrintNode response must not stack runs
// on top of each other.
Schedule::command('labels:reconcile-jobs')->everyTenMinutes()->withoutOverlapping();

// Remove settled print-agent job rows past their 7-day debugging window.
// The PDFs themselves are already dropped at the moment each job settles;
// this is skeleton cleanup, hourly like the SOP pruner.
Schedule::command('labels:prune-print-jobs')->hourly()->withoutOverlapping();

// Drop stored POS report uploads past their 30-day window and unapplied
// batch rows past 90. Applied rows stay — sales_records point at them.
Schedule::command('pos-agent:prune-batches')->dailyAt('03:30')->withoutOverlapping();

// Clear out rendered SOP export PDFs (~12 MB each) past their retention
// window, and fail off any run whose worker died without reporting. Hourly
// rather than daily because the second half of that is what unsticks the
// Training Portal's export button after a deploy restarts the queue worker
// mid-render.
Schedule::command('sop:prune-exports')->hourly()->withoutOverlapping();

// Land approved salary revisions on the day they take effect. Just after
// midnight so a raise dated the 1st is in place before anyone opens payroll
// on the 1st; the command is idempotent (applied_at is the guard), so a
// missed night is caught by the next run rather than double-applying.
Schedule::command('salary:apply-revisions')->dailyAt('00:15');

// Switch off staff the morning after their resignation date, so they leave
// the attendance grid, duty roster and service charge pool from the next
// period onwards but stay on the one they actually worked. Idempotent, so a
// missed night is caught by the next run.
Schedule::command('hr:apply-resignations')->dailyAt('00:20');
