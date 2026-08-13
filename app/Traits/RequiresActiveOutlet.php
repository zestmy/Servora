<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * The outlet a new record belongs to, or a refusal.
 *
 * Four purchasing screens read `activeOutletId() ?? Outlet::where('company_id',
 * $id)->value('id')` — the user's outlet, or failing that WHICHEVER OUTLET
 * CAME FIRST. That second half is the anti-pattern PicksRecordOutlet was
 * written to remove; its docblock says so in as many words: it "replaces the
 * old 'first company outlet' default, which silently filed every multi-outlet
 * record under the wrong location."
 *
 * A purchase order, a delivery and a credit note are money. Filing one against
 * an arbitrary branch because the user happened to have no outlet attached is
 * not a sensible default — it is a wrong number in somebody's stock and cost
 * report, with nothing on screen to say so. Refusing is the honest answer, and
 * the fix is one line of admin: attach the user to an outlet.
 *
 * Nobody on production has no outlet today, so this was latent rather than
 * live. It only needed one admin account created without an outlet.
 */
trait RequiresActiveOutlet
{
    /**
     * The outlet to file a NEW record against.
     *
     * Only for the create path. Do not call it when editing: an existing
     * record already knows its outlet, and re-deriving one from whoever opened
     * the form is how a record migrates to the wrong branch.
     */
    protected function requireActiveOutlet(): int
    {
        $outletId = Auth::user()?->activeOutletId();

        abort_if($outletId === null, 403,
            'Your account is not assigned to an outlet, so there is nowhere to file this record. '
            . 'Ask an administrator to assign you to one.');

        return (int) $outletId;
    }
}
