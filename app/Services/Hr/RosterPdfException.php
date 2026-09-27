<?php

namespace App\Services\Hr;

/**
 * A roster PDF that cannot be read, with a message written for the person
 * who uploaded it — it is shown to them as-is.
 */
class RosterPdfException extends \RuntimeException
{
}
