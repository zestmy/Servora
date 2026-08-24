<?php

namespace App\Services\Labels;

use App\Models\LabelSetting;
use App\Models\LabelTemplate;
use App\Models\ShelfLifeRule;

/**
 * What each label type means and when to reach for it.
 *
 * Lives in code rather than in a Blade file because four screens show it —
 * the manager print screen, the manager set editor, and both of their staff
 * equivalents — and a food-safety instruction that says one thing on the
 * desktop and another on the phone is worse than no instruction.
 *
 * The wording is aimed at whoever is standing at the bench, not at an
 * auditor. Each entry answers three questions in the order they get asked:
 * what is this for, what date will come out, and what is the trap.
 */
class LabelGuide
{
    /**
     * @return array<int, array{key: string, caption: string, title: string, when: string, date: string, watch: ?string, state: ?string}>
     */
    public static function types(): array
    {
        return [
            [
                'key'     => 'prep',
                'caption' => LabelTemplate::LABEL_TYPES['prep'],
                'title'   => 'Prepped in-house',
                'when'    => 'Anything you have made, portioned, decanted or batched yourself — sauces, stocks, '
                    . 'mise en place, a tub filled from a bigger one.',
                'date'    => 'Counts the item\'s chilled shelf life forward from the moment you print.',
                'watch'   => 'Decanting starts a new clock. A sauce moved into a fresh tub gets a fresh label, '
                    . 'not the old one carried over.',
            ],
            [
                'key'     => 'oof',
                'caption' => LabelTemplate::LABEL_TYPES['oof'],
                'title'   => 'Out of the freezer',
                'when'    => 'Put on the moment something leaves the freezer to thaw, not when it finishes thawing.',
                'date'    => 'Counts the THAWING shelf life, which is usually far shorter than the frozen one.',
                'watch'   => 'Thawing is one-way. A defrosted item never goes back to its frozen date and must '
                    . 'not be refrozen.',
            ],
            [
                'key'     => 'received',
                'caption' => LabelTemplate::LABEL_TYPES['received'],
                'title'   => 'Received from a supplier',
                'when'    => 'At goods-in, on deliveries that arrive without a usable date of their own, or where '
                    . 'you need to record when it landed.',
                'date'    => 'Uses the item\'s own storage state and its rule for that state.',
                'watch'   => 'It never extends a supplier\'s date. If the pack says sooner, the pack wins.',
            ],
            [
                'key'     => 'opened',
                'caption' => LabelTemplate::LABEL_TYPES['opened'],
                'title'   => 'Opened for the first time',
                'when'    => 'The first time a sealed pack, tub, bottle or carton is broken into.',
                'date'    => 'Counts the "once opened, use within" life, which is the shortest of them all.',
                'watch'   => 'This is the one people forget. Opening overrides the printed date on the pack — '
                    . 'a three-month-dated carton opened today is not good for three months.',
            ],
            [
                'key'     => 'dry_store',
                'caption' => LabelTemplate::LABEL_TYPES['dry_store'],
                'title'   => 'Ambient / dry store',
                'when'    => 'Dry goods decanted into a storage container — flour, sugar, pulses, spice mixes.',
                'date'    => 'Ambient shelf life, usually long. Some dry goods have no rule and print no date at all.',
                'watch'   => 'A label with no date still earns its place: it says what is in the container '
                    . 'and who filled it.',
            ],
            [
                'key'     => 'hot_hold',
                'caption' => LabelTemplate::LABEL_TYPES['hot_hold'],
                'title'   => 'Held hot for service',
                'when'    => 'The moment cooked food goes into a bain-marie, hot cabinet, carvery or pass — '
                    . 'not when it came out of the oven.',
                'date'    => 'Counts the cooked shelf life forward from the print, so the label says how long '
                    . 'it may stay on service.',
                'watch'   => 'Hot holding is a countdown, not storage. Food that drops below its hold '
                    . 'temperature is on borrowed time whatever the label says, and reheating does not '
                    . 'restart the clock.',
            ],
            [
                'key'     => 'cold_hold',
                'caption' => LabelTemplate::LABEL_TYPES['cold_hold'],
                'title'   => 'Held cold for service',
                'when'    => 'Chilled food put out on display or service — salad bars, deli counters, '
                    . 'buffet lines, a topping rail.',
                'date'    => 'Counts the chilled shelf life forward from the print.',
                'watch'   => 'Going out on display does not extend the item\'s original use-by. If the tub it '
                    . 'came from is due tomorrow, so is what you put on the rail.',
            ],
            [
                'key'     => 'custom',
                'caption' => 'No caption',
                'title'   => 'Custom',
                'when'    => 'Anything the seven above do not describe.',
                'date'    => 'Nothing is calculated — you type the date, and the log marks it as entered by hand.',
                'watch'   => 'Reach for this last. A typed date is not backed by a rule, so it is the one an '
                    . 'auditor will ask about.',
            ],
        ];
    }

    /**
     * Storage states with the range that prints on the label.
     *
     * Ranges come from the company's own settings where they have been
     * edited, so this shows what will actually print rather than the
     * built-in defaults.
     *
     * @return array<int, array{key: string, label: string, range: ?string}>
     */
    public static function states(?int $companyId = null): array
    {
        $temps = $companyId
            ? LabelSetting::temperaturesFor($companyId)
            : ShelfLifeRule::STORAGE_TEMPERATURES;

        $out = [];

        foreach (ShelfLifeRule::STORAGE_STATES as $key => $label) {
            $out[] = [
                'key'   => $key,
                'label' => $label,
                // 'opened' deliberately has no range: it describes the state
                // of the pack, not a temperature to hold it at.
                'range' => $temps[$key] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Where a use-by comes from, most specific first. Worth stating plainly:
     * "why is this date wrong" is answered by knowing which rule won.
     *
     * @return array<int, string>
     */
    public static function dateSources(): array
    {
        return [
            'A rule set on the item itself, for the storage state you picked.',
            'Otherwise, the rule on its category.',
            'Otherwise, the shelf life recorded on the recipe.',
            'Otherwise, nothing is calculated and you type the date — the log flags it as manual.',
        ];
    }
}
