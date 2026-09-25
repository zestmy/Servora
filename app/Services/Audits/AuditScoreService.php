<?php

namespace App\Services\Audits;

use App\Models\Audit;
use App\Models\AuditLine;
use App\Models\AuditSection;
use App\Models\AuditTemplateSection;

/**
 * The only place an audit's score is worked out.
 *
 * THE ARITHMETIC, per section, over its scorable leaves:
 *
 *   total      = Σ points of every leaf
 *   na         = Σ points of leaves marked N/A
 *   available  = total − na                              ("Points (A)" on the ROSE form)
 *   lost       = Σ points_lost of leaves marked NC
 *   score      = available − lost                        ("Score (B)")
 *   percent    = score / available × 100                 (null when nothing was applicable)
 *
 * For the audit as a whole, only AREA sections pool:
 *
 *   available  = Σ available of area sections
 *   lost       = Σ lost of area sections
 *   penalty    = Σ lost of PENALTY sections               ("Main N.C.")
 *   score      = available − lost − penalty               ("Score (C)")
 *   percent    = score / available × 100
 *
 * A penalty section's points are never in the pool — its deductions come off
 * the total after the areas are added up. This is how a critical food-safety
 * section works on the sample form: 14 items that are not "part of the bar",
 * whose failure costs the whole audit.
 *
 * Writes the cached columns on the section and the audit; nothing else may.
 * Reads one aggregate query per audit, so it is cheap enough to run on every
 * tap while an audit is being conducted.
 */
class AuditScoreService
{
    public function recalculate(Audit $audit): Audit
    {
        $sections = $audit->sections()->get();

        // One pass over the scorable leaves, bucketed by section in PHP —
        // no SUM(CASE …) so the same code runs on SQLite in tests.
        $leaves = AuditLine::query()
            ->where('audit_id', $audit->id)
            ->where('is_leaf', true)
            ->where('type', '!=', 'info')
            ->get(['audit_section_id', 'points', 'result', 'points_lost']);

        $bySection = $leaves->groupBy('audit_section_id');

        $poolAvailable = 0;
        $poolLost      = 0;
        $penalty       = 0;
        $poolTotal     = 0;
        $poolNa        = 0;
        $findings      = 0;

        foreach ($sections as $section) {
            $rows = $bySection->get($section->id, collect());

            $total    = (int) $rows->sum('points');
            $na       = (int) $rows->where('result', AuditLine::RESULT_NA)->sum('points');
            $lost     = (int) $rows->where('result', AuditLine::RESULT_NC)->sum('points_lost');
            $answered = $rows->whereNotNull('result')->count();
            $ncCount  = $rows->where('result', AuditLine::RESULT_NC)->count();

            $available = max(0, $total - $na);
            $score     = $available - $lost;

            $section->forceFill([
                'total_points'     => $total,
                'na_points'        => $na,
                'available_points' => $available,
                'lost_points'      => $lost,
                'score_points'     => $score,
                'score_percent'    => $available > 0 ? round($score / $available * 100, 2) : null,
                'answered_count'   => $answered,
                'leaf_count'       => $rows->count(),
            ])->save();

            $findings += $ncCount;

            if ($section->scoring_mode === AuditTemplateSection::MODE_PENALTY) {
                $penalty += $lost;
            } else {
                $poolTotal     += $total;
                $poolNa        += $na;
                $poolAvailable += $available;
                $poolLost      += $lost;
            }
        }

        $score = $poolAvailable - $poolLost - $penalty;

        $audit->forceFill([
            'total_points'     => $poolTotal,
            'na_points'        => $poolNa,
            'available_points' => $poolAvailable,
            'lost_points'      => $poolLost,
            'penalty_points'   => $penalty,
            'score_points'     => $score,
            'score_percent'    => $poolAvailable > 0 ? round($score / $poolAvailable * 100, 2) : null,
            'finding_count'    => $findings,
        ])->save();

        return $audit;
    }

    /** Leaves still waiting for a result, per section — what blocks a submit. */
    public function unanswered(Audit $audit): int
    {
        return AuditLine::query()
            ->where('audit_id', $audit->id)
            ->where('is_leaf', true)
            ->where('type', '!=', 'info')
            ->whereNull('result')
            ->count();
    }

    /**
     * The colour a percentage deserves. Thresholds are the ones QA teams
     * conventionally print on the summary page; a company-level setting can
     * replace them later without the views changing.
     */
    public static function band(?float $percent): string
    {
        if ($percent === null) {
            return 'none';
        }

        return match (true) {
            $percent >= 90 => 'good',
            $percent >= 75 => 'fair',
            default        => 'poor',
        };
    }
}
