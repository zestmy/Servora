<?php

namespace App\Livewire\Labels\Staff;

use App\Http\Middleware\LabelStaffAuthenticate;
use App\Livewire\Staff\StaffLogin;

/**
 * Label app sign-in. Everything is in StaffLogin — this only says which app
 * it is.
 */
class Login extends StaffLogin
{
    protected function intendedKey(): string
    {
        return LabelStaffAuthenticate::INTENDED_KEY;
    }

    protected function fallbackRoute(): string
    {
        return 'labels.staff.print';
    }

    protected function layoutName(): string
    {
        return 'layouts.labels-staff';
    }

    protected function tagline(): string
    {
        return 'Food safety labels';
    }

    /** A luggage tag, matching the labels mark in the sidebar. */
    protected function iconPath(): string
    {
        return 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.997 1.997 0 013 12V7a4 4 0 014-4z';
    }
}
