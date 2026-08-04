<?php

namespace App\Services\Hr;

use RuntimeException;

/**
 * A punch that was refused outright rather than recorded and flagged.
 *
 * The message is shown verbatim to the employee standing at the door, so it
 * has to say what to DO next, not what went wrong internally.
 */
class ClockInException extends RuntimeException
{
}
