<?php

namespace App\Livewire\Clock\Staff;

/**
 * How this person signs in, and how to change it.
 *
 * The switch used to be a one-time question on the login screen, asked right
 * after an email sign-in. Two things were wrong with that: an offer that
 * appears once and then never again is not a setting, so somebody who tapped
 * past it had no way back to it; and asking it THERE meant parking a signed-in
 * person on the sign-in screen, which is what made a reload look like being
 * thrown out.
 *
 * It is a screen now, reached by tapping your own name in the header — the
 * place a person looks for "things about me" — and it says what is currently
 * true rather than only offering a change.
 */
class Account extends StaffComponent
{
    public function enablePinLogin(): void
    {
        $this->staff()->forceFill(['pin_login_disabled_at' => null])->save();

        session()->flash('success', 'Your PIN works again. You can still ask for an email code any time.');
    }

    /**
     * Switch the PIN off — but never leave somebody with no way in.
     *
     * The email route needs an address on the record. Without one, turning the
     * PIN off would lock this person out of the app they are standing in, and
     * the only way back would be a manager reissuing a PIN.
     */
    public function disablePinLogin(): void
    {
        $employee = $this->staff();

        if (blank($employee->email)) {
            session()->flash('error', 'You need an email address on your record first — ask your manager to add one.');

            return;
        }

        $employee->forceFill(['pin_login_disabled_at' => now()])->save();

        session()->flash('success', 'Your PIN is switched off. Sign in with an emailed code from now on.');
    }

    public function render()
    {
        return view('livewire.clock.staff.account')
            ->layout('layouts.clock-staff', $this->shell('Account'));
    }
}
