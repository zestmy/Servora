<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make room for the company logo on templates that are still stock.
 *
 * The logo field was added to DefaultTemplates, which only affects templates
 * seeded from now on. Every company already using labels has its templates in
 * the database, so without this they would keep printing the old arrangement
 * and the feature would appear not to work.
 *
 * ONLY untouched templates are rewritten. A layout is considered untouched
 * when every positioned field still matches the previous stock arrangement
 * exactly — same tokens, same millimetres, same sizes. Anything a chef has
 * moved in the designer is left alone: a migration that silently reflows
 * someone's customised label is worse than one that does nothing, and they
 * can add the logo themselves from the designer in two clicks.
 */
return new class extends Migration
{
    /** The stock arrangement as it was before the logo. */
    private const PREVIOUS = [
        ['token' => 'item.name',           'x' => 3,  'y' => 3,    'w' => 64, 'h' => 9, 'font_size' => 12, 'weight' => 'bold',   'align' => 'left'],
        ['token' => 'label.caption',       'x' => 3,  'y' => 12.5, 'w' => 34, 'h' => 4, 'font_size' => 7,  'weight' => 'normal', 'align' => 'left'],
        ['token' => 'date.end',            'x' => 3,  'y' => 16.5, 'w' => 64, 'h' => 8, 'font_size' => 18, 'weight' => 'bold',   'align' => 'left'],
        ['token' => 'date.start',          'x' => 3,  'y' => 25,   'w' => 40, 'h' => 4, 'font_size' => 7,  'weight' => 'normal', 'align' => 'left'],
        ['token' => 'quantity',            'x' => 44, 'y' => 25,   'w' => 23, 'h' => 4, 'font_size' => 7,  'weight' => 'normal', 'align' => 'right'],
        ['token' => 'staff.name',          'x' => 3,  'y' => 29.5, 'w' => 33, 'h' => 4, 'font_size' => 7,  'weight' => 'normal', 'align' => 'left'],
        ['token' => 'storage.instruction', 'x' => 37, 'y' => 29.5, 'w' => 30, 'h' => 4, 'font_size' => 7,  'weight' => 'bold',   'align' => 'right'],
        ['token' => 'footer',              'x' => 3,  'y' => 34,   'w' => 64, 'h' => 4, 'font_size' => 6,  'weight' => 'normal', 'align' => 'left'],
    ];

    public function up(): void
    {
        $target = \App\Services\Labels\DefaultTemplates::FIELDS;

        $this->rewrite(self::PREVIOUS, $target);
    }

    /**
     * Reversible: a template still on the new stock layout goes back to the
     * old one. A customised one is left alone in both directions.
     */
    public function down(): void
    {
        $this->rewrite(\App\Services\Labels\DefaultTemplates::FIELDS, self::PREVIOUS);
    }

    private function rewrite(array $from, array $to): void
    {
        DB::table('label_templates')->orderBy('id')->each(function ($row) use ($from, $to) {
            $layout = json_decode((string) $row->layout, true);

            if (! is_array($layout) || ! isset($layout['fields']) || ! is_array($layout['fields'])) {
                return;
            }

            if (! $this->matches($layout['fields'], $from)) {
                return;
            }

            $layout['fields'] = $to;

            DB::table('label_templates')->where('id', $row->id)
                ->update(['layout' => json_encode($layout), 'updated_at' => now()]);
        });
    }

    /**
     * Field-by-field comparison on the properties that define a layout.
     *
     * Not a strict === on the arrays: templates saved through the designer
     * carry extra keys (an empty 'text', for one) and their numbers come back
     * as floats where the seed wrote integers. Comparing the meaningful
     * properties, loosely typed, is what makes "untouched" mean what a chef
     * would mean by it.
     */
    private function matches(array $fields, array $reference): bool
    {
        if (count($fields) !== count($reference)) {
            return false;
        }

        foreach ($reference as $i => $want) {
            $have = $fields[$i] ?? null;

            if (! is_array($have)) {
                return false;
            }

            if (($have['token'] ?? null) !== $want['token']) {
                return false;
            }

            foreach (['x', 'y', 'w', 'h', 'font_size'] as $key) {
                if (abs((float) ($have[$key] ?? -1) - (float) $want[$key]) > 0.001) {
                    return false;
                }
            }

            $haveWeight = ($have['weight'] ?? 'normal') === 'bold' ? 'bold' : 'normal';
            $wantWeight = ($want['weight'] ?? 'normal') === 'bold' ? 'bold' : 'normal';

            if ($haveWeight !== $wantWeight) {
                return false;
            }

            if (($have['align'] ?? 'left') !== ($want['align'] ?? 'left')) {
                return false;
            }
        }

        return true;
    }
};
