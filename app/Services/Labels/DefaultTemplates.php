<?php

namespace App\Services\Labels;

use App\Models\LabelTemplate;

/**
 * Stock label layouts, one per label type, at 70 × 40 mm — the label stock
 * this deployment standardised on.
 *
 * All types share one field arrangement. They differ only in the caption,
 * which the renderer pulls from LabelTemplate::LABEL_TYPES. Fields whose
 * value resolves to nothing are dropped at render time, so a dry-store label
 * with no use-by simply prints without that line rather than showing a gap.
 */
class DefaultTemplates
{
    public const WIDTH_MM  = 70.0;
    public const HEIGHT_MM = 40.0;

    /** @return array<string, array{name: string, layout: array}> */
    public static function all(): array
    {
        $templates = [];

        foreach (LabelTemplate::LABEL_TYPES as $type => $caption) {
            $templates[$type] = [
                'name'   => self::nameFor($type),
                'layout' => ['fields' => self::fields()],
            ];
        }

        return $templates;
    }

    private static function nameFor(string $type): string
    {
        return match ($type) {
            'prep'      => 'Standard use-by',
            'oof'       => 'Standard defrost',
            'received'  => 'Standard received',
            'opened'    => 'Standard opened',
            'dry_store' => 'Standard dry store',
            'custom'    => 'Standard custom',
            default     => 'Standard ' . str_replace('_', ' ', $type),
        };
    }

    /**
     * 3 mm margins on a 70 × 40 label leave 64 × 34 usable.
     *
     * The use-by is by far the largest element. It is the one thing a chef
     * reads across a chiller at arm's length and the one thing an auditor
     * checks, so it gets 18pt while everything else sits at 7–8.
     *
     * The extra height over the old 50 × 25 stock buys three things the
     * small label had no room for: an item name that can run to two lines
     * without truncating, a use-by nearly twice the size, and a footer.
     */
    public const FIELDS = [
        [
            'token' => 'item.name',
            'x' => 3, 'y' => 3, 'w' => 64, 'h' => 9,
            'font_size' => 12, 'weight' => 'bold', 'align' => 'left',
        ],
        [
            'token' => 'label.caption',
            'x' => 3, 'y' => 12.5, 'w' => 34, 'h' => 4,
            'font_size' => 7, 'align' => 'left',
        ],
        [
            'token' => 'date.end',
            'x' => 3, 'y' => 16.5, 'w' => 64, 'h' => 8,
            'font_size' => 18, 'weight' => 'bold', 'align' => 'left',
        ],
        [
            'token' => 'date.start',
            'x' => 3, 'y' => 25, 'w' => 40, 'h' => 4,
            'font_size' => 7, 'align' => 'left',
        ],
        [
            'token' => 'quantity',
            'x' => 44, 'y' => 25, 'w' => 23, 'h' => 4,
            'font_size' => 7, 'align' => 'right',
        ],
        [
            'token' => 'staff.name',
            'x' => 3, 'y' => 29.5, 'w' => 33, 'h' => 4,
            'font_size' => 7, 'align' => 'left',
        ],
        [
            'token' => 'storage.instruction',
            'x' => 37, 'y' => 29.5, 'w' => 30, 'h' => 4,
            'font_size' => 7, 'weight' => 'bold', 'align' => 'right',
        ],
        [
            'token' => 'footer',
            'x' => 3, 'y' => 34, 'w' => 64, 'h' => 4,
            'font_size' => 6, 'align' => 'left',
        ],
    ];

    private static function fields(): array
    {
        return self::FIELDS;
    }
}
