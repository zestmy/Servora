<?php

namespace App\Services\Labels;

/**
 * How a freeform item name is written onto a label.
 *
 * Ingredients, recipes and prep items are all uppercased when they are saved,
 * so a label printed from one comes out in caps. A freeform name typed at the
 * print screen used to go through untouched, which put "chicken stock" on the
 * shelf next to "CHICKEN STOCK" and made a chiller look like two different
 * systems. Uppercase is also simply easier to read at arm's length on a 203
 * dpi thermal print, which is the actual job.
 *
 * Whitespace is collapsed at the same time: a double space typed on a tablet
 * keyboard is invisible in the input and very visible on the label.
 */
class LabelName
{
    public static function normalise(?string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) $name));

        // mb_ so an accented or non-Latin name is not mangled into bytes.
        return mb_strtoupper((string) $name, 'UTF-8');
    }
}
