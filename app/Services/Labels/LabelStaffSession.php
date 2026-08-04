<?php

namespace App\Services\Labels;

use App\Services\Staff\StaffSession;

/**
 * The labels app's name for the shared staff PIN session.
 *
 * The implementation moved to StaffSession when the clock-in app arrived and
 * needed the same thing. This subclass stays because it is type-hinted
 * throughout the labels screens, and because "the labels session" is still
 * how those screens read best.
 *
 * @see StaffSession for the behaviour, including why the session key keeps
 *      its labels-era name.
 */
class LabelStaffSession extends StaffSession
{
}
