<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\PresenceHeartbeat;
use App\Services\Staff\StaffSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The Staff Portal saying "I am open", and where, if it may.
 *
 * A plain controller rather than a Livewire action: this fires from every
 * screen in the app including ones with no component of their own, it wants
 * to be fire-and-forget, and Livewire's round trip would re-render a page
 * nobody asked to re-render.
 *
 * NO BODY IN THE RESPONSE. 204 on every path — recorded, throttled, or
 * ignored for want of a fix. The client has nothing to do with the answer,
 * and a response that distinguished "your location was stored" from "it was
 * discarded as too vague" would be a probe for finding the accuracy threshold
 * from outside.
 */
class StaffHeartbeatController extends Controller
{
    public function store(Request $request, StaffSession $staff, PresenceHeartbeat $heartbeat): Response
    {
        /*
         * Re-resolved from the session, never read off the request.
         *
         * The middleware already proved somebody is signed in; this is what
         * decides WHOSE row gets written, and an employee_id in the payload
         * would let anybody with a staff PIN post a location onto a colleague.
         */
        $employee = $staff->employee($staff->companyId());

        if (! $employee) {
            return response()->noContent();
        }

        $heartbeat->record($employee, [
            'latitude'  => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'accuracy'  => $request->input('accuracy'),
        ]);

        return response()->noContent();
    }
}
