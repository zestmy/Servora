<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Staff\StaffSession;
use Illuminate\Http\RedirectResponse;

/**
 * Signing out of the staff clock-in app.
 *
 * A plain POST route rather than a Livewire action, because the control that
 * calls it lives in the LAYOUT — outside any component's root element, where
 * Livewire never binds a wire:click and the button is silently inert. That is
 * exactly how it shipped, and how it was reported: a sign-out button that did
 * nothing at all.
 *
 * Being a form post also means it keeps working on a device where the app's
 * JavaScript has failed, which is the moment somebody most needs to get out
 * and hand the phone to a manager.
 */
class ClockSessionController extends Controller
{
    public function destroy(StaffSession $session): RedirectResponse
    {
        $session->signOut();

        return redirect()->route('clock.staff.login');
    }
}
