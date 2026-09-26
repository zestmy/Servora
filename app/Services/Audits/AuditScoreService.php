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
    /**
     * THE OUTCOME — pass, conditional pass or fail — is a judgement over three
     * things, because a percentage alone misreads this kind of form: the
     * critical items cost so little of the pool that an outlet can fail five
     * of them and still score 93%.
     *
     *   fail          total below `conditional_percent`, OR more than
     *                 `max_major_conditional` major NCs, OR any area section
     *                 below `section_conditional_percent`
     *   pass          total at or above `pass_percent`, AND no more than
     *                 `max_major_pass` major NCs, AND every area section at
     *                 or above `section_pass_percent`
     *   conditional   everything in between — re-audit within `reaudit_days`
     *
     * A major NC is an NC in a penalty section. Sections with nothing
     * applicable are skipped. These are the defaults; a form overrides any of
     * them, and each audit keeps the rules it was started under.
     */
    public const DEFAULT_RULES = [
        'pass_percent'                => 90,
        'conditional_percent'         => 80,
        'section_pass_percent'        => 80,
        'section_conditional_percent' => 70,
        'max_major_pass'              => 0,
        'max_major_conditional'       => 1,
        'reaudit_days'                => 30,
    ];

    public const RULE_LABELS = [
        'pass_percent'                => 'Pass: total score at least (%)',
        'conditional_percent'         => 'Conditional: total score at least (%)',
        'section_pass_percent'        => 'Pass: every area section at least (%)',
        'section_conditional_percent' => 'Conditional: every area section at least (%)',
        'max_major_pass'              => 'Pass: major NCs allowed',
        'max_major_conditional'       => 'Conditional: major NCs allowed',
        'reaudit_days'                => 'Conditional pass: re-audit within (days)',
    ];

    /**
     * @param  array<int, array{name:string, percent:?float}>  $areaSections
     * @return array{outcome:?string, reasons:array<int,string>}
     */
    public static function evaluate(?float $percent, int $majors, array $areaSections, array $rules): array
    {
        $r = array_replace(self::DEFAULT_RULES, $rules);

        if ($percent === null) {
            return ['outcome' => null, 'reasons' => []];
        }

        $fail = [];
        $cond = [];

        if ($percent < $r['conditional_percent']) {
            $fail[] = sprintf('Total %.1f%% is below %s%%', $percent, $r['conditional_percent']);
        } elseif ($percent < $r['pass_percent']) {
            $cond[] = sprintf('Total %.1f%% is below %s%%', $percent, $r['pass_percent']);
        }

        if ($majors > $r['max_major_conditional']) {
            $fail[] = $majors . ' major non-conformance' . ($majors === 1 ? '' : 's') . ' (more than ' . $r['max_major_conditional'] . ' allowed)';
        } elseif ($majors > $r['max_major_pass']) {
            $cond[] = $majors . ' major non-conformance' . ($majors === 1 ? '' : 's') . ' (' . ($r['max_major_pass'] === 0 ? 'none' : 'at most ' . $r['max_major_pass']) . ' allowed for a pass)';
        }

        foreach ($areaSections as $section) {
            if ($section['percent'] === null) {
                continue;
            }
            if ($section['percent'] < $r['section_conditional_percent']) {
                $fail[] = sprintf('%s at %.1f%% is below %s%%', $section['name'], $section['percent'], $r['section_conditional_percent']);
            } elseif ($section['percent'] < $r['section_pass_percent']) {
                $cond[] = sprintf('%s at %.1f%% is below %s%%', $section['name'], $section['percent'], $r['section_pass_percent']);
            }
        }

        if ($fail) {
            return ['outcome' => Audit::OUTCOME_FAIL, 'reasons' => array_merge($fail, $cond)];
        }
        if ($cond) {
            return ['outcome' => Audit::OUTCOME_CONDITIONAL, 'reasons' => $cond];
        }

        return ['outcome' => Audit::OUTCOME_PASS, 'reasons' => []];
    }

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
        $majors        = 0;
        $areaSections  = [];

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
                $majors  += $ncCount;
            } else {
                $areaSections[] = ['name' => $section->name, 'percent' => $available > 0 ? round($score / $available * 100, 2) : null];
                $poolTotal     += $total;
                $poolNa        += $na;
                $poolAvailable += $available;
                $poolLost      += $lost;
            }
        }

        $score   = $poolAvailable - $poolLost - $penalty;
        $percent = $poolAvailable > 0 ? round($score / $poolAvailable * 100, 2) : null;

        // Graded on every recalculation so a draft shows where it is heading;
        // the list and the PDF only read it once the audit is submitted.
        $verdict = self::evaluate($percent, $majors, $areaSections, $audit->outcomeRules());

        $audit->forceFill([
            'outcome'          => $verdict['outcome'],
            'outcome_reasons'  => $verdict['reasons'],
            'major_count'      => $majors,
            'total_points'     => $poolTotal,
            'na_points'        => $poolNa,
            'available_points' => $poolAvailable,
            'lost_points'      => $poolLost,
            'penalty_points'   => $penalty,
            'score_points'     => $score,
            'score_percent'    => $percent,
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
