<?php

if (! function_exists('ordinal')) {
    /**
     * Format an integer with its English ordinal suffix (1 => "1st", 22 => "22nd").
     */
    function ordinal(int $number): string
    {
        if (in_array($number % 100, [11, 12, 13], true)) {
            $suffix = 'th';
        } else {
            $suffix = ['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th';
        }

        return $number . $suffix;
    }
}
