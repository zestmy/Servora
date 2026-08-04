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

    protected function tagline(): string
    {
        return 'Staff attendance';
    }

    /** A clock face. */
    protected function iconPath(): string
    {
        return 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
    }
}
