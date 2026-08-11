<?php

namespace App\Support;

/**
 * What a cost percentage means, decided once.
 *
 * The 30% and 35% lines were written as bare literals in five places — both
 * dashboard gauge blocks, the COGS table, the chef alert and the shared alert
 * builder — so "the target" was five independent numbers that happened to
 * agree. They now come from config/costing.php, and the mapping from a figure
 * to a tone and a word lives here so the gauge and the table can never reach
 * different verdicts about the same number.
 *
 * The word matters as much as the tone. cogs-table already appended a
 * visually-hidden "over target" to each figure, with a comment explaining that
 * roughly one man in twelve cannot separate that green from that red — but the
 * gauges one file over carried their verdict in colour alone, which is the
 * same bug the same author had already fixed. One implementation now, so
 * fixing it once is fixing it everywhere.
 */
final class CostVerdict
{
    /**
     * @return array{tone: string, bar: string, word: string}
     */
    public static function for(float $pct): array
    {
        $over = (float) config('costing.target.over');
        $near = (float) config('costing.target.near');

        return match (true) {
            $pct > $over => ['tone' => 'text-danger-700',  'bar' => 'bg-danger-500',  'word' => 'over target'],
            $pct > $near => ['tone' => 'text-warning-700', 'bar' => 'bg-warning-500', 'word' => 'near target'],
            default      => ['tone' => 'text-success-700', 'bar' => 'bg-success-500', 'word' => 'on target'],
        };
    }

    /**
     * The reading with no judgement attached.
     *
     * On day 2 of a month the cost percentage is computed off a sample far too
     * small to act on, and was still being painted red or green as though it
     * were a finding — so a slow first weekend looked like a crisis every
     * month. Below the configured day count the figure still shows, in ink,
     * and says why it is not calling it.
     */
    public static function pending(): array
    {
        return ['tone' => 'text-gray-900', 'bar' => 'bg-gray-400', 'word' => 'too early to call'];
    }

    /** Has enough of the window elapsed for the verdict to mean anything? */
    public static function isReady(int $daysElapsed): bool
    {
        return $daysElapsed >= (int) config('costing.min_days_for_verdict');
    }
}
