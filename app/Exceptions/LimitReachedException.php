<?php

namespace App\Exceptions;

use Exception;

class LimitReachedException extends Exception
{
    public function __construct(
        public readonly string $metric,
        public readonly int $current,
        public readonly int $limit,
    ) {
        $label = str_replace('_', ' ', $metric);
        parent::__construct("You have reached your plan limit of {$limit} {$label}. Please upgrade to add more.");
    }
}
