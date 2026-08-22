<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The PDF of a subscription invoice, for the two audiences that may read one.
 *
 * Authorisation is decided here rather than by route middleware because the
 * two audiences are allowed different things: a system admin may pull any
 * invoice in any state, while a customer may only pull their OWN company's,
 * and only once it has left draft. A draft is the platform's working copy —
 * its numbers can still change, and it must never be downloadable as a
 * document.
 */
class SubscriptionInvoicePdfController extends Controller
{
    public function __invoke(Request $request, int $id, InvoiceService $invoices)
    {
        $invoice = Invoice::with(['company', 'subscription.plan', 'payment'])->findOrFail($id);
        $user    = Auth::user();

        if (! $user->isSystemRole()) {
            // company_id is the ACTIVE company pointer, which is exactly the
            // right question here: the invoice belongs to whichever company
            // the user is currently standing in, not to every company they
            // are a member of.
            abort_unless($invoice->company_id === $user->company_id, 403);
            abort_if($invoice->isDraft(), 404);
        }

        return $invoices->pdf($invoice)->download($invoices->filename($invoice));
    }
}
