<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * An outlet id that belongs to the signed-in user's company.
 *
 * `exists:outlets,id` queries the table directly, so it bypasses CompanyScope
 * and accepts any id in the table — including another tenant's. That is fine
 * right up until the value is written somewhere, and several screens wrote it:
 * a stock transfer destination, a label printer's outlet, a central kitchen's
 * base outlet. The kitchen one also attached this company's users to whatever
 * outlet was named, which turns a lax validation rule into cross-tenant
 * membership.
 *
 * The dropdown being company-scoped is not the control. A Livewire property is
 * client-supplied like any other request field, and `wire:model` on a <select>
 * does not stop a crafted payload naming an id that was never in the list.
 */
trait ValidatesCompanyOutlet
{
    /** An outlet in this company that has not been deleted. */
    protected function outletExistsRule(): Exists
    {
        return Rule::exists('outlets', 'id')
            ->where('company_id', Auth::user()->company_id)
            ->whereNull('deleted_at');
    }

    /**
     * As above, and still open for business.
     *
     * Use this where the value is being chosen NOW. Do not use it to validate
     * a stored value being re-saved — an outlet can be deactivated after the
     * fact, and rejecting it then turns "edit the notes on an old record" into
     * an error the user cannot clear.
     */
    protected function activeOutletExistsRule(): Exists
    {
        return $this->outletExistsRule()->where('is_active', true);
    }
}
