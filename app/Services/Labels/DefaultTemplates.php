<?php

namespace App\Services\Labels;

use App\Models\LabelTemplate;

/**
 * Stock label layouts, one per label type, at 50 × 25 mm.
 *
 * 50 × 25 is the placeholder until the physical label stock is confirmed —
 * the designer will let companies build their own sizes, and these exist so
 * a chef can print something useful on day one without opening it.
 *
 * All types share one field arrangement. They differ only in the caption,
 * which the renderer pulls from LabelTemplate::LABEL_TYPES. Fields whose
 * value resolves to nothing are dropped at render time, so a dry-store label
 * with no use-by simply prints without that line rather than showing a gap.
 */
class DefaultTemplates
{
    public const WIDTH_MM  = 50.0;
    public const HEIGHT_MM = 25.0;

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
     * 2 mm margins on a 50 mm label leave 46 mm of usable width.
     *
     * The use-by is the largest element on the label by a wide margin: it is
     * the one thing a chef reads across a chiller at arm's length, and it is
     * the thing an auditor checks.
     */
    private static function fields(): array
    {
        return [
            [
                'token' => 'item.name',
                'x' => 2, 'y' => 1.5, 'w' => 46, 'h' => 5,
                'font_size' => 9, 'weight' => 'bold', 'align' => 'left',
            ],
            [
                'token' => 'label.caption',
                'x' => 2, 'y' => 7, 'w' => 24, 'h' => 3.5,
                'font_size' => 6, 'align' => 'left',
            ],
            [
                'token' => 'date.end',
                'x' => 2, 'y' => 10.5, 'w' => 46, 'h' => 5.5,
                'font_size' => 11, 'weight' => 'bold', 'align' => 'left',
            ],
            [
                'token' => 'date.start',
                'x' => 2, 'y' => 16.5, 'w' => 30, 'h' => 3.5,
                'font_size' => 6, 'align' => 'left',
            ],
            [
                'token' => 'staff.name',
                'x' => 2, 'y' => 20, 'w' => 24, 'h' => 3.5,
                'font_size' => 6, 'align' => 'left',
            ],
            [
                'token' => 'storage.instruction',
                'x' => 26, 'y' => 20, 'w' => 22, 'h' => 3.5,
                'font_size' => 6, 'align' => 'right',
            ],
        ];
    }
}
