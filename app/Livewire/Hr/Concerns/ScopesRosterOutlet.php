<?php

namespace App\Livewire\Hr\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Outlet scoping for the roster settings screens.
 *
 * RosterApprover, RosterStation, RosterEmailRecipient and RosterSetting carry
 * an outlet_id and NO CompanyScope, and every one of these screens takes its
 * outlet from a client-writable `$outletId`. So a bare findOrFail($id), or a
 * write against whatever outletId the browser sent, reaches any company's
 * rows. Everything here goes through the user's accessible outlets instead —
 * which are always inside the active company (User::accessibleOutlets()).
 */
trait ScopesRosterOutlet
{
    /** @return array<int, int> */
    protected function rosterOutletIds(): array
    {
        return Auth::user()?->accessibleOutletIds() ?? [];
    }

    protected function canUseRosterOutlet(?int $outletId): bool
    {
        return $outletId !== null && in_array((int) $outletId, $this->rosterOutletIds(), true);
    }

    /** Refuse an action aimed at an outlet this user cannot reach. */
    protected function assertRosterOutlet(?int $outletId): void
    {
        abort_unless($this->canUseRosterOutlet($outletId), 403);
    }

    /**
     * Snap a forged or stale $outletId back to one the user may use, so the
     * screen never LISTS another outlet's rows either.
     */
    protected function normaliseRosterOutlet(): void
    {
        if ($this->outletId !== null && ! $this->canUseRosterOutlet($this->outletId)) {
            $fallback = Auth::user()?->activeOutletId();
            $this->outletId = $this->canUseRosterOutlet($fallback) ? $fallback : null;
        }
    }
}
