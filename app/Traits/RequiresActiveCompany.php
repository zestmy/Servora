<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * For screens that edit ONE company's settings.
 *
 * A platform administrator (Super Admin / System Admin) has no active company
 * — `users.company_id` is null and they hold no company_user membership,
 * because they operate above companies rather than inside one. CompanySwitcher
 * hides the company block for them entirely for the same reason.
 *
 * EnsureCompanyScope sends an ordinary user with no company back to the login
 * screen, but deliberately waves system roles through so they can reach
 * Admin > Companies, which sits inside the same company-scoped route group. So
 * the only accounts that arrive here without a company are system roles.
 *
 * The settings hub already branches — systemGroups() for them, companyGroups()
 * for everyone else — so none of these screens are ever linked to a platform
 * admin. Typing the URL was another matter: `forCompany(null)` against an `int`
 * parameter is a TypeError, so the screen answered a stray bookmark with a 500.
 * Nine settings screens did this.
 */
trait RequiresActiveCompany
{
    /**
     * The active company id, or a 403 explaining why there isn't one.
     *
     * Returns int so callers can hand it straight to the `int`-typed
     * forCompany()/seedDefaults() helpers that were the crash site.
     */
    protected function requireActiveCompany(): int
    {
        $companyId = Auth::user()?->company_id;

        abort_if($companyId === null, 403,
            'These settings belong to a single company, and your account is not scoped to one. '
            . 'Platform administrators manage companies from Admin > Companies.');

        return (int) $companyId;
    }
}
