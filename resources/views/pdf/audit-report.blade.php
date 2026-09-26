@extends('pdf.layout')

@section('title', ($audit->template_code ?: $audit->template_name) . ' — ' . ($audit->outlet?->name ?? ''))

@section('content')
    @php
        $pct    = $audit->score_percent !== null ? (float) $audit->score_percent : null;
        $colour = fn (?float $p) => $p === null ? '#475569' : ($p >= 90 ? '#15803d' : ($p >= 75 ? '#b45309' : '#b91c1c'));
        $cell   = 'padding: 5px 8px; border: 1px solid #e5e7eb; font-size: 9pt; vertical-align: top;';
        $head   = 'padding: 5px 8px; border: 1px solid #e5e7eb; font-size: 8pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.4px; background: #f9fafb;';
        $findingCount = $findings->flatten(1)->count();
        $majorCount   = $findings->flatten(1)->where('severity', 'major')->count();
        $openCount    = $findings->flatten(1)->where('status', 'open')->count();
    @endphp

    {{-- ── Page 1: the audit and its score ─────────────────────────── --}}
    <div class="header">
        <div class="header-left">
            @if ($logo)
                <img src="{{ $logo }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $company?->name ?? 'Company' }}</div>
        </div>
        <div class="header-right">
            <div class="doc-title">{{ $audit->template_name }}</div>
            <div class="doc-number">{{ $audit->reference_number ?: (($audit->template_code ?: 'AUDIT') . '-' . $audit->id) }}</div>
            <div class="doc-status">{{ $audit->statusLabel() }}</div>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="{{ $head }} width: 16%;">Outlet</td>
            <td style="{{ $cell }} width: 34%;">{{ $audit->outlet?->name }}</td>
            <td style="{{ $head }} width: 16%;">Date</td>
            <td style="{{ $cell }} width: 34%;">{{ $audit->audit_date->format('d M Y') }} · {{ $audit->time_in ? substr($audit->time_in, 0, 5) : '—' }} – {{ $audit->time_out ? substr($audit->time_out, 0, 5) : '—' }}</td>
        </tr>
        <tr>
            <td style="{{ $head }}">Auditor</td>
            <td style="{{ $cell }}">{{ $audit->auditor?->name ?? '—' }}</td>
            <td style="{{ $head }}">Submitted</td>
            <td style="{{ $cell }}">{{ $audit->submitted_at?->format('d M Y H:i') ?? '—' }}</td>
        </tr>
        @foreach (collect($audit->header_values ?? [])->chunk(2) as $pair)
            <tr>
                @foreach ($pair as $field)
                    <td style="{{ $head }}">{{ $field['label'] }}</td>
                    <td style="{{ $cell }}">{{ $field['value'] ?: '—' }}</td>
                @endforeach
                @if ($pair->count() === 1)<td style="{{ $head }}"></td><td style="{{ $cell }}"></td>@endif
            </tr>
        @endforeach
        @if ($audit->notes)
            <tr>
                <td style="{{ $head }}">Notes</td>
                <td style="{{ $cell }}" colspan="3">{{ $audit->notes }}</td>
            </tr>
        @endif
    </table>

    @if ($audit->outcome)
        @php
            $oc = ['pass' => ['#15803d', '#f0fdf4'], 'conditional' => ['#b45309', '#fffbeb'], 'fail' => ['#b91c1c', '#fef2f2']][$audit->outcome];
            $rules = $audit->outcomeRules();
        @endphp
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
            <tr>
                <td style="padding: 10px 12px; border: 2px solid {{ $oc[0] }}; background: {{ $oc[1] }};">
                    <div style="font-size: 8pt; color: {{ $oc[0] }}; text-transform: uppercase; letter-spacing: 0.5px;">Outcome</div>
                    <div style="font-size: 18pt; font-weight: bold; color: {{ $oc[0] }};">{{ strtoupper($audit->outcomeLabel()) }}</div>
                    @if ($audit->outcome_reasons)
                        <ul style="margin: 4px 0 0 14px; padding: 0; font-size: 9pt; color: {{ $oc[0] }};">
                            @foreach ($audit->outcome_reasons as $reason)<li>{{ $reason }}</li>@endforeach
                        </ul>
                    @endif
                    @if ($audit->outcome === 'conditional')
                        <div style="font-size: 9pt; color: {{ $oc[0] }}; margin-top: 4px;">Re-audit within {{ $rules['reaudit_days'] }} days.</div>
                    @endif
                    <div style="font-size: 7.5pt; color: #475569; margin-top: 6px;">
                        Pass: total ≥ {{ $rules['pass_percent'] }}%, major NCs ≤ {{ $rules['max_major_pass'] }}, every area ≥ {{ $rules['section_pass_percent'] }}%.
                        Conditional: total ≥ {{ $rules['conditional_percent'] }}%, major NCs ≤ {{ $rules['max_major_conditional'] }}, every area ≥ {{ $rules['section_conditional_percent'] }}%. Otherwise fail.
                    </div>
                </td>
            </tr>
        </table>
    @endif

    {{-- Total score, large --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            <td style="{{ $cell }} width: 40%; background: #f8fafc; text-align: center; padding: 14px 8px;">
                <div style="font-size: 8pt; color: #475569; text-transform: uppercase; letter-spacing: 0.4px;">Total score</div>
                <div style="font-size: 34pt; font-weight: bold; line-height: 1.1; color: {{ $colour($pct) }};">{{ $pct === null ? '—' : number_format($pct, 2) . '%' }}</div>
                <div style="font-size: 9pt; color: #334155; margin-top: 4px;">
                    {{ $audit->score_points }} of {{ $audit->available_points }} points
                    @if ($audit->na_points) · {{ $audit->na_points }} N/A @endif
                </div>
                @if ($audit->penalty_points)
                    <div style="font-size: 9pt; color: #b91c1c; margin-top: 2px;">−{{ $audit->penalty_points }} critical penalty</div>
                @endif
            </td>
            <td style="{{ $cell }} width: 20%; text-align: center; padding: 14px 8px;">
                <div style="font-size: 8pt; color: #475569; text-transform: uppercase;">Non-conformances</div>
                <div style="font-size: 22pt; font-weight: bold; color: {{ $findingCount ? '#b91c1c' : '#15803d' }};">{{ $findingCount }}</div>
            </td>
            <td style="{{ $cell }} width: 20%; text-align: center; padding: 14px 8px;">
                <div style="font-size: 8pt; color: #475569; text-transform: uppercase;">Major</div>
                <div style="font-size: 22pt; font-weight: bold; color: {{ $majorCount ? '#b91c1c' : '#15803d' }};">{{ $majorCount }}</div>
            </td>
            <td style="{{ $cell }} width: 20%; text-align: center; padding: 14px 8px;">
                <div style="font-size: 8pt; color: #475569; text-transform: uppercase;">Still open</div>
                <div style="font-size: 22pt; font-weight: bold; color: {{ $openCount ? '#b45309' : '#15803d' }};">{{ $openCount }}</div>
            </td>
        </tr>
    </table>

    {{-- Section cards --}}
    <div class="section-header">Score by section</div>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <thead>
            <tr>
                <th style="{{ $head }}">Section</th>
                <th style="{{ $head }} width: 12%; text-align: right;">Points</th>
                <th style="{{ $head }} width: 10%; text-align: right;">N/A</th>
                <th style="{{ $head }} width: 12%; text-align: right;">Available</th>
                <th style="{{ $head }} width: 10%; text-align: right;">Lost</th>
                <th style="{{ $head }} width: 12%; text-align: right;">Score</th>
                <th style="{{ $head }} width: 14%; text-align: right;">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($audit->sections as $s)
                @php $sp = $s->score_percent !== null ? (float) $s->score_percent : null; @endphp
                <tr>
                    <td style="{{ $cell }}">
                        <strong>{{ $s->name }}</strong>
                        @if ($s->name_alt)<div style="font-size: 8pt; color: #475569;">{{ $s->name_alt }}</div>@endif
                        @if ($s->isPenalty())<div style="font-size: 8pt; color: #b91c1c;">Critical items — deducted from the total</div>@endif
                    </td>
                    <td style="{{ $cell }} text-align: right;">{{ $s->total_points }}</td>
                    <td style="{{ $cell }} text-align: right;">{{ $s->na_points ?: '—' }}</td>
                    <td style="{{ $cell }} text-align: right;">{{ $s->available_points }}</td>
                    <td style="{{ $cell }} text-align: right; color: {{ $s->lost_points ? '#b91c1c' : '#334155' }};">{{ $s->lost_points ? '−' . $s->lost_points : '0' }}</td>
                    <td style="{{ $cell }} text-align: right;">{{ $s->isPenalty() ? '—' : $s->score_points }}</td>
                    <td style="{{ $cell }} text-align: right; font-weight: bold; font-size: 11pt; color: {{ $s->isPenalty() ? ($s->lost_points ? '#b91c1c' : '#15803d') : $colour($sp) }};">
                        {{ $s->isPenalty() ? '−' . $s->lost_points : ($sp === null ? '—' : number_format($sp, 1) . '%') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Sign-off --}}
    <table style="width: 100%; border-collapse: collapse; margin: 6px 0 0;">
        <tr>
            <td style="{{ $cell }} width: 50%; height: 64px;">
                <div style="font-size: 8pt; color: #475569; text-transform: uppercase;">Checked by</div>
                <div style="margin-top: 22px;">{{ $audit->auditor?->name ?? '—' }}</div>
            </td>
            <td style="{{ $cell }} width: 50%;">
                <div style="font-size: 8pt; color: #475569; text-transform: uppercase;">Acknowledged by</div>
                @if ($audit->isAcknowledged())
                    @if ($signature)<img src="{{ $signature }}" style="height: 36px; display: block; margin-top: 2px;" alt="">@endif
                    <div>{{ $audit->acknowledged_by_name }} · {{ $audit->acknowledged_by_position }}</div>
                    <div style="font-size: 8pt; color: #475569;">{{ $audit->acknowledged_at?->format('d M Y H:i') }}</div>
                @elseif ($audit->requires_acknowledgement)
                    <div style="margin-top: 26px; border-top: 1px solid #94a3b8; font-size: 8pt; color: #475569;">Name / Position / Date</div>
                @else
                    <div style="margin-top: 22px; font-size: 8pt; color: #475569;">Not required for this form</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Score history: this outlet, this form, the last twelve months. Bars
         drawn with widths rather than a chart — dompdf runs no script, and a
         table the reader can also read is the honest version of a chart. --}}
    @if (isset($history) && $history->count() > 1)
        <div class="section-header" style="margin-top: 12px;">Score history — {{ $audit->outlet?->name }}, {{ $audit->template_code ?: $audit->template_name }}, last {{ \App\Http\Controllers\Audits\AuditReportController::HISTORY_MONTHS }} months</div>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
            <thead>
                <tr>
                    <th style="{{ $head }} width: 16%;">Audit</th>
                    <th style="{{ $head }} width: 12%; text-align: right;">Score</th>
                    <th style="{{ $head }} width: 12%; text-align: right;">Change</th>
                    <th style="{{ $head }} width: 18%;">Outcome</th>
                    <th style="{{ $head }}">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($history as $h)
                    @php
                        $bar = ['pass' => '#15803d', 'conditional' => '#b45309', 'fail' => '#b91c1c'][$h['outcome']] ?? '#475569';
                    @endphp
                    <tr style="{{ $h['current'] ? 'background: #f8fafc; font-weight: bold;' : '' }}">
                        <td style="{{ $cell }}">{{ $h['date']->format('d M Y') }}{{ $h['current'] ? ' (this audit)' : '' }}</td>
                        <td style="{{ $cell }} text-align: right; color: {{ $colour($h['score']) }};">{{ number_format($h['score'], 1) }}%</td>
                        <td style="{{ $cell }} text-align: right; color: {{ $h['delta'] === null ? '#475569' : ($h['delta'] > 0 ? '#15803d' : ($h['delta'] < 0 ? '#b91c1c' : '#334155')) }};">
                            {{ $h['delta'] === null ? '—' : (($h['delta'] > 0 ? '+' : '') . number_format($h['delta'], 1)) }}
                        </td>
                        <td style="{{ $cell }} color: {{ $bar }};">{{ $h['label'] ?? '—' }}</td>
                        <td style="{{ $cell }} padding: 5px 8px;">
                            <div style="width: 100%; height: 9px; background: #f1f5f9;">
                                <div style="width: {{ max(1, min(100, (int) round($h['score']))) }}%; height: 9px; background: {{ $bar }};"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @php $first = $history->first(); $last = $history->last(); $trend = round($last['score'] - $first['score'], 1); @endphp
        <p style="font-size: 8.5pt; color: #475569; margin: 0;">
            {{ $history->count() }} audits since {{ $first['date']->format('d M Y') }}:
            {{ $trend > 0 ? 'up ' . number_format($trend, 1) : ($trend < 0 ? 'down ' . number_format(abs($trend), 1) : 'unchanged') }} points overall,
            {{ $history->where('outcome', 'pass')->count() }} passed.
        </p>
    @endif

    {{-- ── Page 2+: the non-conformances ────────────────────────────── --}}
    @if ($findings->isNotEmpty())
        <div style="page-break-before: always;"></div>
        <div class="section-header">Non-conformances &amp; corrective actions</div>
        <p style="font-size: 8.5pt; color: #475569; margin: 0 0 8px;">
            {{ $findingCount }} item{{ $findingCount === 1 ? '' : 's' }} marked NC
            @if ($majorCount) · {{ $majorCount }} major @endif
            · {{ $openCount }} still open. Passed and not-applicable items are not listed; the full checklist is on screen.
        </p>

        @foreach ($findings as $sectionName => $group)
            <div style="font-size: 10pt; font-weight: bold; margin: 10px 0 4px; padding-bottom: 3px; border-bottom: 2px solid #e5e7eb; color: #0f172a;">
                {{ $sectionName }} <span style="font-weight: normal; color: #475569; font-size: 9pt;">· {{ $group->count() }}</span>
            </div>

            @foreach ($group as $finding)
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px; page-break-inside: avoid;">
                    <tr>
                        <td style="{{ $cell }} border-left: 4px solid {{ $finding->isMajor() ? '#b91c1c' : '#f59e0b' }}; background: #fff;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="vertical-align: top; padding: 0;">
                                        <div style="font-size: 10pt; font-weight: bold; color: #0f172a;">
                                            @if ($finding->isMajor())<span style="color: #b91c1c;">MAJOR · </span>@endif{{ $finding->item_label }}
                                        </div>
                                        @if ($finding->description)
                                            <div style="font-size: 9pt; color: #334155; margin-top: 3px;">{{ $finding->description }}</div>
                                        @endif
                                    </td>
                                    <td style="vertical-align: top; padding: 0; width: 90px; text-align: right;">
                                        <div style="font-size: 9pt; font-weight: bold; color: #b91c1c;">−{{ $finding->points_lost }} pt</div>
                                        <div style="font-size: 8pt; color: {{ $finding->isResolved() ? '#15803d' : '#b45309' }};">{{ $finding->isResolved() ? 'Resolved' : 'Open' }}</div>
                                    </td>
                                </tr>
                            </table>

                            @if ($finding->photos->isNotEmpty())
                                <div style="margin-top: 6px;">
                                    @foreach ($finding->photos as $photo)
                                        @if (isset($thumbs[$photo->id]))
                                            <img src="{{ $thumbs[$photo->id] }}" style="width: 118px; height: auto; margin: 0 4px 4px 0; border: 1px solid #e5e7eb; vertical-align: top;" alt="">
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #e5e7eb;">
                                <div style="font-size: 8pt; color: #475569; text-transform: uppercase; letter-spacing: 0.4px;">Corrective action</div>
                                @forelse ($finding->actions as $action)
                                    <div style="margin-top: 3px; font-size: 9pt; color: #0f172a;">
                                        {{ $action->description }}
                                        <div style="font-size: 8pt; color: #475569;">
                                            {{ $action->owner?->name ?? 'No owner' }}{{ $action->owner?->designation ? ' · ' . $action->owner->designation : '' }}
                                            @if ($action->due_date) · due {{ $action->due_date->format('d M Y') }} @endif
                                            · <strong style="color: {{ $action->isVerified() ? '#15803d' : '#334155' }};">{{ $action->statusLabel() }}</strong>
                                            @if ($action->isVerified()) by {{ $action->verifiedBy?->name }} on {{ $action->verified_at?->format('d M Y') }} @endif
                                        </div>
                                        @if ($action->completion_note)<div style="font-size: 8pt; color: #334155;">“{{ $action->completion_note }}”</div>@endif
                                        @php $ev = $action->photos->where('kind', 'evidence'); $ve = $action->photos->where('kind', 'verification'); @endphp
                                        @if ($ev->isNotEmpty() || $ve->isNotEmpty())
                                            <div style="margin-top: 4px;">
                                                @foreach ($ev as $p)
                                                    @if (isset($actionThumbs[$p->id]))<img src="{{ $actionThumbs[$p->id] }}" style="width: 72px; height: auto; margin: 0 3px 3px 0; border: 1px solid #e5e7eb; vertical-align: top;" alt="">@endif
                                                @endforeach
                                                @foreach ($ve as $p)
                                                    @if (isset($actionThumbs[$p->id]))<img src="{{ $actionThumbs[$p->id] }}" style="width: 72px; height: auto; margin: 0 3px 3px 0; border: 2px solid #15803d; vertical-align: top;" alt="">@endif
                                                @endforeach
                                                <div style="font-size: 7.5pt; color: #475569;">
                                                    @if ($ev->isNotEmpty()) {{ $ev->count() }} photo{{ $ev->count() === 1 ? '' : 's' }} of the fix @endif
                                                    @if ($ev->isNotEmpty() && $ve->isNotEmpty()) · @endif
                                                    @if ($ve->isNotEmpty())<span style="color: #15803d;">{{ $ve->count() }} verification photo{{ $ve->count() === 1 ? '' : 's' }} (green border)</span>@endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div style="margin-top: 3px; font-size: 9pt; color: #b45309;">No action raised yet</div>
                                @endforelse
                            </div>
                        </td>
                    </tr>
                </table>
            @endforeach
        @endforeach
    @endif
@endsection
