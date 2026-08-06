<?php

namespace App\Livewire\Clock\Staff;

use App\Http\Middleware\ClockStaffAuthenticate;
use App\Livewire\Staff\StaffLogin;

/**
 * Clock-in app sign-in. Everything is in StaffLogin — this only says which
 * app it is.
 */
class Login extends StaffLogin
{
    protected function intendedKey(): string
    {
        return ClockStaffAuthenticate::INTENDED_KEY;
    }

    protected function fallbackRoute(): string
    {
        return 'clock.staff.punch';
    }

    protected function layoutName(): string
    {
        return 'layouts.clock-staff';
    }

    /**
     * "Staff Portal", not "Staff attendance".
     *
     * The app holds punches, leave and time off now. Telling someone the sign
     * above the door says "attendance" and then asking them to apply for
     * annual leave behind it is the kind of small wrongness that makes people
     * think they are in the wrong place.
     */
    protected function tagline(): string
    {
        return 'Staff Portal';
    }

    /**
     * An ID badge rather than a clock face — the app is no longer only about
     * clocking, and the icon is the first thing that says so.
     */
    protected function iconPath(): string
    {
        return 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 012-2h0a2 2 0 012 2v1m-4 0h4m-6 5a2 2 0 104 0 2 2 0 00-4 0zm-1 6a3 3 0 016 0m2-5h3m-3 3h3';
    }
}
