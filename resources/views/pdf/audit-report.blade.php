@extends('pdf.layout')

@section('title', ($audit->template_code ?: $audit->template_name) . ' — ' . ($audit->outlet?->name ?? ''))

@section('content')
    @php
        $pct  = $audit->score_percent !== null ? (float) $audit->score_percent : null;
        $colour = fn (?float $p) => $p === null ? '#475569' : ($p >= 90 ? '#15803d' : ($p >= 75 ? '#b45309' : '#b91c1c'));
        $cell = 'padding: 5px 8px; border: 1px solid #e5e7eb; font-size: 9pt; vertical-align: top;';
        $head = 'padding: 5px 8px; border: 1px solid #e5e7eb; font-size: 8pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.4px; background: #f9fafb;';
    @endphp

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

    {{-- Facts --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
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
    </table>

    {{-- Score cards --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="{{ $cell }} width: 25%; background: #f8fafc;">
                <div style="font-size: 8pt; color: #475569; text-transform: uppercase;">Total score</div>
                <div style="font-size: 20pt; font-weight: bold; color: {{ $colour($pct) }};">{{ $pct === null ? '—' : number_format($pct, 2) . '%' }}</div>
                <div style="font-size: 8pt; color: #475569;">
                    {{ $audit->score_points }} / {{ $audit->available_points }} pts
                    @if ($audit->penalty_points) · −{{ $audit->penalty_points }} penalty @endif
                    · {{ $audit->finding_count }} NC
                </div>
            </td>
            @foreach ($audit->sections as $s)
                @php $sp = $s->score_percent !== null ? (float) $s->score_percent : null; @endphp
                <td style="{{ $cell }}">
                    <div style="font-size: 8pt; color: #475569; text-transform: uppercase;">{{ $s->name }}</div>
                    @if ($s->isPenalty())
                        <div style="font-size: 16pt; font-weight: bold; color: {{ $s->lost_points ? '#b91c1c' : '#15803d' }};">−{{ $s->lost_points }}</div>
                        <div style="font-size: 8pt; color: #475569;">penalty</div>
                    @else
                        <div style="font-size: 16pt; font-weight: bold; color: {{ $colour($sp) }};">{{ $sp === null ? '—' : number_format($sp, 1) . '%' }}</div>
                        <div style="font-size: 8pt; color: #475569;">{{ $s->score_points }} / {{ $s->available_points }}@if ($s->na_points) · N/A {{ $s->na_points }}@endif</div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    {{-- Findings --}}
    <div class="section-header">Non-conformances &amp; corrective actions</div>
    @if ($findings->isEmpty())
        <p style="font-size: 9pt; color: #475569;">No non-conformances recorded.</p>
    @else
        @foreach ($findings as $sectionName => $group)
            <div style="font-size: 9pt; font-weight: bold; margin: 8px 0 4px; color: #0f172a;">{{ $sectionName }}</div>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
                <thead>
                    <tr>
                        <th style="{{ $head }} width: 34%;">Finding</th>
                        <th style="{{ $head }} width: 8%; text-align: center;">Lost</th>
                        <th style="{{ $head }} width: 20%;">Photos</th>
                        <th style="{{ $head }}">Corrective action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group as $finding)
                        <tr>
                            <td style="{{ $cell }}">
                                @if ($finding->isMajor())<span style="color: #b91c1c; font-weight: bold;">MAJOR · </span>@endif
                                {{ $finding->item_label }}
                                @if ($finding->description)
                                    <div style="color: #334155; margin-top: 2px;">{{ $finding->description }}</div>
                                @endif
                            </td>
                            <td style="{{ $cell }} text-align: center;">−{{ $finding->points_lost }}</td>
                            <td style="{{ $cell }}">
                                @foreach ($finding->photos as $photo)
                                    @if (isset($thumbs[$photo->id]))
                                        <img src="{{ $thumbs[$photo->id] }}" style="width: 56px; height: auto; margin: 0 3px 3px 0; border: 1px solid #e5e7eb;" alt="">
                                    @endif
                                @endforeach
                            </td>
                            <td style="{{ $cell }}">
                                @forelse ($finding->actions as $action)
                                    <div style="margin-bottom: 4px;">
                                        {{ $action->description }}
                                        <div style="font-size: 8pt; color: #475569;">
                                            {{ $action->owner?->name ?? 'No owner' }}{{ $action->owner?->designation ? ' · ' . $action->owner->designation : '' }}
                                            @if ($action->due_date) · due {{ $action->due_date->format('d M Y') }} @endif
                                            · <strong>{{ $action->statusLabel() }}</strong>
                                            @if ($action->isVerified()) by {{ $action->verifiedBy?->name }} @endif
                                        </div>
                                    </div>
                                @empty
                                    <span style="color: #b45309;">No action raised yet</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif

    {{-- Acknowledgement --}}
    @if ($audit->requires_acknowledgement)
        <table style="width: 100%; border-collapse: collapse; margin: 10px 0 14px;">
            <tr>
                <td style="{{ $cell }} width: 50%;">
                    <div style="font-size: 8pt; color: #475569; text-transform: uppercase;">Checked by</div>
                    <div style="margin-top: 14px;">{{ $audit->auditor?->name ?? '—' }}</div>
                </td>
                <td style="{{ $cell }} width: 50%;">
                    <div style="font-size: 8pt; color: #475569; text-transform: uppercase;">Acknowledged by</div>
                    @if ($audit->isAcknowledged())
                        @if ($signature)<img src="{{ $signature }}" style="height: 40px; display: block; margin-top: 4px;" alt="">@endif
                        <div>{{ $audit->acknowledged_by_name }} · {{ $audit->acknowledged_by_position }}</div>
                        <div style="font-size: 8pt; color: #475569;">{{ $audit->acknowledged_at?->format('d M Y H:i') }}</div>
                    @else
                        <div style="margin-top: 26px; border-top: 1px solid #94a3b8; font-size: 8pt; color: #475569;">Name / Position / Date</div>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    {{-- The whole form as scored --}}
    <div style="page-break-before: always;"></div>
    <div class="section-header">Checklist</div>
    @foreach ($audit->sections as $s)
        <div style="font-size: 10pt; font-weight: bold; margin: 10px 0 4px; color: #0f172a;">
            {{ $s->name }}@if ($s->name_alt) <span style="font-weight: normal; color: #475569;">/ {{ $s->name_alt }}</span>@endif
            <span style="float: right; font-weight: normal; font-size: 9pt; color: #475569;">
                @if ($s->isPenalty()) −{{ $s->lost_points }} penalty @else {{ $s->score_points }} / {{ $s->available_points }} @endif
            </span>
        </div>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
            <thead>
                <tr>
                    <th style="{{ $head }} width: 6%;">No.</th>
                    <th style="{{ $head }}">Item</th>
                    <th style="{{ $head }} width: 8%; text-align: center;">Pts</th>
                    <th style="{{ $head }} width: 8%; text-align: center;">Result</th>
                    <th style="{{ $head }} width: 8%; text-align: center;">Lost</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tree[$s->id] ?? [] as $node)
                    @php $l = $node['line']; @endphp
                    <tr>
                        <td style="{{ $cell }} {{ $node['children']->isNotEmpty() ? 'font-weight: bold;' : '' }}">{{ $l->number }}</td>
                        <td style="{{ $cell }} {{ $node['children']->isNotEmpty() ? 'font-weight: bold;' : '' }}">
                            {{ $l->label }}@if ($l->subject) — {{ $l->subject }}@endif
                            @if ($l->label_alt)<div style="font-size: 8pt; color: #475569; font-weight: normal;">{{ $l->label_alt }}</div>@endif
                            @if ($l->type === 'info' && $l->info_value)<div style="color: #334155;">{{ $l->info_value }}</div>@endif
                            @if ($l->is_leaf && $l->note)<div style="font-size: 8pt; color: #b91c1c;">{{ $l->note }}</div>@endif
                        </td>
                        <td style="{{ $cell }} text-align: center;">{{ $l->is_leaf && $l->type !== 'info' ? $l->points : '' }}</td>
                        <td style="{{ $cell }} text-align: center;">{{ $l->is_leaf ? strtoupper((string) $l->result) : '' }}</td>
                        <td style="{{ $cell }} text-align: center;">{{ $l->result === 'nc' ? $l->points_lost : '' }}</td>
                    </tr>
                    @foreach ($node['children'] as $c)
                        <tr>
                            <td style="{{ $cell }} padding-left: 16px; color: #475569;">{{ $c->number }}</td>
                            <td style="{{ $cell }} padding-left: 16px;">
                                {{ $c->label }}
                                @if ($c->label_alt)<div style="font-size: 8pt; color: #475569;">{{ $c->label_alt }}</div>@endif
                                @if ($c->hint)<div style="font-size: 8pt; color: #0f766e;">{{ $c->hint }}</div>@endif
                                @if ($c->note)<div style="font-size: 8pt; color: #b91c1c;">{{ $c->note }}</div>@endif
                            </td>
                            <td style="{{ $cell }} text-align: center;">{{ $c->type !== 'info' ? $c->points : '' }}</td>
                            <td style="{{ $cell }} text-align: center;">{{ strtoupper((string) $c->result) }}</td>
                            <td style="{{ $cell }} text-align: center;">{{ $c->result === 'nc' ? $c->points_lost : '' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endforeach
@endsection
