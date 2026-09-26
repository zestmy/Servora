<?php

namespace App\Livewire\Reports\Audits;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditSection;
use App\Models\AuditTemplate;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Audit scores over time: is each outlet getting better, and where does it
 * keep losing points.
 *
 * Reads only cached columns — audits.score_percent and audit_sections.* —
 * plus the findings table for the "most failed items" list. Never the lines.
 * Draft audits are excluded throughout: a running score is not a result.
 *
 * Bucketing by month is done in PHP after a plain date query so the same
 * code runs on SQLite in tests (no DATE_FORMAT).
 */
class AuditTrend extends Component
{
    use ReportFilters, ScopesToActiveOutlet;

    public string $templateFilter = '';

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    protected function defaultQuickRange(): string
    {
        return 'all';
    }

    public function updatedTemplateFilter(): void
    {
    }

    /** Hook the pagination-shaped trait calls onto a page that has no pagination. */
    public function resetPage(): void
    {
    }

    private function audits()
    {
        $q = Audit::query()
            ->with('outlet')
            ->where('status', '!=', Audit::STATUS_DRAFT)
            ->whereNotNull('score_percent');

        $this->scopeByOutlet($q);

        if ($this->outletFilter) {
            $q->where('outlet_id', (int) $this->outletFilter);
        }
        if ($this->templateFilter) {
            $q->where('audit_template_id', (int) $this->templateFilter);
        }
        if ($this->dateFrom) {
            $q->whereDate('audit_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $q->whereDate('audit_date', '<=', $this->dateTo);
        }

        return $q->orderBy('audit_date')->orderBy('id')->get();
    }

    public function exportCsv()
    {
        $audits = $this->audits();

        return $this->exportCsvDownload('audit-trend.csv',
            ['Date', 'Outlet', 'Form', 'Score %', 'Outcome', 'Major NCs', 'Available', 'Lost', 'Penalty', 'Findings', 'Status'],
            $audits->map(fn ($a) => [
                $a->audit_date->toDateString(), $a->outlet?->name, $a->template_code ?: $a->template_name,
                $a->score_percent, $a->outcomeLabel(), $a->major_count, $a->available_points, $a->lost_points, $a->penalty_points, $a->finding_count, $a->status,
            ])->all()
        );
    }

    public function render()
    {
        $audits   = $this->audits();
        $auditIds = $audits->pluck('id');

        // ── Per outlet ───────────────────────────────────────────────────
        $byOutlet = $audits->groupBy('outlet_id')->map(function ($rows) {
            $scores = $rows->pluck('score_percent')->map(fn ($v) => (float) $v);
            $latest = $rows->last();
            $prev   = $rows->count() > 1 ? $rows[$rows->count() - 2] : null;

            return [
                'outlet'  => $latest->outlet?->name ?? '—',
                'count'   => $rows->count(),
                'latest'  => (float) $latest->score_percent,
                'latestOutcome' => $latest->outcome,
                'latestOutcomeLabel' => $latest->outcomeLabel(),
                'passes'  => $rows->where('outcome', 'pass')->count(),
                'latestOn' => $latest->audit_date,
                'latestId' => $latest->id,
                'average' => round($scores->avg(), 1),
                'best'    => $scores->max(),
                'worst'   => $scores->min(),
                'delta'   => $prev ? round((float) $latest->score_percent - (float) $prev->score_percent, 1) : null,
                'series'  => $scores->all(),
                'openFindings' => AuditFinding::whereIn('audit_id', $rows->pluck('id'))->where('status', 'open')->count(),
            ];
        })->sortBy('outlet')->values();

        // ── Chart: one line per outlet, x = audit date ───────────────────
        $chart = [
            'datasets' => $byOutlet->map(function ($o, $i) use ($audits) {
                $rows = $audits->filter(fn ($a) => ($a->outlet?->name ?? '—') === $o['outlet']);

                return [
                    'label' => $o['outlet'],
                    'data'  => $rows->map(fn ($a) => ['x' => $a->audit_date->toDateString(), 'y' => (float) $a->score_percent])->values()->all(),
                ];
            })->values()->all(),
        ];

        // ── Per section: average % across the audits in range ───────────
        $sections = AuditSection::whereIn('audit_id', $auditIds)
            ->get(['name', 'scoring_mode', 'score_percent', 'lost_points', 'available_points'])
            ->groupBy('name')
            ->map(fn ($rows, $name) => [
                'name'    => $name,
                'penalty' => $rows->first()->scoring_mode === 'penalty',
                'average' => $rows->first()->scoring_mode === 'penalty'
                    ? null
                    : round($rows->whereNotNull('score_percent')->avg('score_percent') ?? 0, 1),
                'lost'    => (int) $rows->sum('lost_points'),
                'audits'  => $rows->count(),
            ])
            ->sortBy(fn ($s) => $s['penalty'] ? -1 : ($s['average'] ?? 101))
            ->values();

        // ── Most failed items ────────────────────────────────────────────
        $mostFailed = AuditFinding::whereIn('audit_id', $auditIds)
            ->get(['item_label', 'section_name', 'severity', 'points_lost'])
            ->groupBy(fn ($f) => $f->section_name . '|' . $f->item_label)
            ->map(fn ($rows) => [
                'label'   => $rows->first()->item_label,
                'section' => $rows->first()->section_name,
                'major'   => $rows->first()->severity === 'major',
                'times'   => $rows->count(),
                'lost'    => (int) $rows->sum('points_lost'),
            ])
            ->sortByDesc(fn ($r) => [$r['times'], $r['lost']])
            ->take(15)
            ->values();

        $all = $audits->pluck('score_percent')->map(fn ($v) => (float) $v);

        return view('livewire.reports.audits.audit-trend', [
            'audits'     => $audits,
            'byOutlet'   => $byOutlet,
            'chart'      => $chart,
            'sections'   => $sections,
            'mostFailed' => $mostFailed,
            'average'    => $all->isNotEmpty() ? round($all->avg(), 1) : null,
            'outcomeCounts' => [
                'pass'        => $audits->where('outcome', 'pass')->count(),
                'conditional' => $audits->where('outcome', 'conditional')->count(),
                'fail'        => $audits->where('outcome', 'fail')->count(),
            ],
            'outlets'    => $this->filterableOutlets(),
            'templates'  => AuditTemplate::ordered()->get(['id', 'name', 'code']),
            'quickRangeOptions' => static::quickRangeOptions(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Audit Score Trend']);
    }
}
