<?php

namespace App\Livewire\Labels\Staff;

use App\Services\Labels\LabelStaffSession;

/**
 * Account screen: change your own PIN, or sign out.
 *
 * Changing a PIN signs every session out — including this one — because
 * the session's fingerprint no longer matches. That is the intended
 * behaviour: if someone changes their PIN because a colleague watched them
 * type the old one, a tablet still signed in on the old PIN would defeat
 * the point. They are signed straight back in here.
 */
class Pin extends StaffComponent
{
    public string $current = '';

    public string $new = '';

    public string $confirm = '';

    public function mount(): void
    {
        $this->mountStaffDefaults();
    }

    public function changePin(LabelStaffSession $session): void
    {
        $this->resetValidation();

        $staff = $this->staff();

        if (! $staff->verifyLabelPin($this->current)) {
            $this->addError('current', 'That is not your current PIN.');

            return;
        }

        if (! preg_match('/^\d{4,6}$/', $this->new)) {
            $this->addError('new', 'Use 4 to 6 digits.');

            return;
        }

        if ($this->new !== $this->confirm) {
            $this->addError('confirm', 'The two PINs do not match.');

            return;
        }

        if ($this->new === $this->current) {
            $this->addError('new', 'Choose a different PIN from your current one.');

            return;
        }

        $staff->setLabelPin($this->new);

        // The old fingerprint is now stale everywhere. Re-issue for this
        // device so the person changing it isn't kicked out mid-shift.
        $session->signIn($staff->refresh());

        $this->current = $this->new = $this->confirm = '';

        session()->flash('success', 'PIN changed. Any other device is now signed out.');
    }

    public function signOut(LabelStaffSession $session)
    {
        $session->signOut();

        return $this->redirectRoute('labels.staff.login', navigate: false);
    }

    public function render()
    {
        return view('livewire.labels.staff.pin')
            ->layout('layouts.labels-staff', $this->shell('Your PIN'));
    }
}
