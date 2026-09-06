@extends('pdf.layout')

@section('title', $docTitle)

{{--
    One shared shape for Wastage, Staff Meal and Transfer summaries: a range
    of records totalled and broken into groups (department, outlet, whichever
    the caller passes), with the detail rows that made up each group filed
    underneath it. Purchases keeps its own richer report — it has a
    linked/hand-typed supplier merge and a month-by-month chart that don't
    apply here — but these three are otherwise the same document, so they
    are one template rather than three copies that would drift apart.

    Charts are tables and coloured blocks, the way every other PDF report in
    this app draws them: dompdf has no canvas.
--}}

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 2);
        $pct   = fn ($v) => number_format((float) $v, 1) . '%';
    @endphp

    {{-- ═══ Header ═══════════════════════════════════════════════════════ --}}
    <div class="header">
        <div class="header-left">
            @if ($company?->logo)
                <img src="{{ public_path('storage/' . $company->logo) }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $company?->name ?? 'Company' }}</div>
            @if ($company?->registration_number)
                <div class="company-detail" style="font-size: 8.5pt; color: #6b7280;">Reg No: {{ $company->registration_number }}</div>
            @endif
        </div>
        <div class="header-right">
            <div class="doc-title">{{ $docTitle }}</div>
            <div class="doc-number">{{ \Illuminate\Support\Carbon::parse($scope['from'])->format('d M Y') }} &ndash; {{ \Illuminate\Support\Carbon::parse($scope['to'])->format('d M Y') }}</div>
            <div style="font-size: 8.5pt; color: #6b7280; margin-top: 2px;">{{ number_format($totals['days']) }} {{ \Illuminate\Support\Str::plural('day', $totals['days']) }}</div>
        </div>
    </div>

    {{-- What the reader is looking at. A summary with the filters left off
         invites the wrong conclusion from a correct number. --}}
    <table id="summary" style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1px solid #e5e7eb;">
        @foreach ($scopeRows->chunk(2) as $pair)
            <tr>
                @foreach ($pair as [$label, $value])
                    <td style="width: 13%; padding: 5px 10px; background: #f9fafb; font-size: 8pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; {{ $loop->parent->last ? '' : 'border-bottom: 1px solid #e5e7eb;' }}">{{ $label }}</td>
                    <td style="width: 37%; padding: 5px 10px; font-size: 9pt; color: #0f172a; {{ $loop->last ? '' : 'border-right: 1px solid #e5e7eb;' }} {{ $loop->parent->last ? '' : 'border-bottom: 1px solid #e5e7eb;' }}">{{ $value }}</td>
                @endforeach
                @if ($pair->count() === 1)
                    <td colspan="2" style="{{ $loop->last ? '' : 'border-bottom: 1px solid #e5e7eb;' }}"></td>
                @endif
            </tr>
        @endforeach
    </table>

    {{-- ═══ Stat cards ═══════════════════════════════════════════════════ --}}
    <table style="width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 5px;">
        <tr>
            <td style="width: 25%; border: 1px solid #e5e7eb; border-top: 2.5px solid #0b7677; padding: 7px 10px;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px;">Total {{ strtolower($valueLabel) }}</div>
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a; white-space: nowrap;"><span style="font-size: 8pt; color: #64748b;">RM</span> {{ $money($totals['value']) }}</div>
                <div style="font-size: 7.5pt; color: #94a3b8;">RM {{ $money($totals['perDay']) }} per day</div>
            </td>
            <td style="width: 25%; border: 1px solid #e5e7eb; border-top: 2.5px solid #43bdb8; padding: 7px 10px;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px;">{{ ucfirst(\Illuminate\Support\Str::plural($noun, 2)) }}</div>
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a; white-space: nowrap;">{{ number_format($totals['count']) }}</div>
                <div style="font-size: 7.5pt; color: #94a3b8;">RM {{ $money($totals['average']) }} average</div>
            </td>
            <td style="width: 25%; border: 1px solid #e5e7eb; border-top: 2.5px solid #1d4ed8; padding: 7px 10px;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px;">{{ $groupLabel }}s</div>
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a; white-space: nowrap;">{{ number_format($totals['groups']) }}</div>
            </td>
            <td style="width: 25%; border: 1px solid #e5e7eb; border-top: 2.5px solid #7c3aed; padding: 7px 10px;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px;">Biggest {{ strtolower($groupLabel) }}</div>
                <div style="font-size: 10pt; font-weight: bold; color: #0f172a; line-height: 1.25;">{{ $totals['topName'] }}</div>
                <div style="font-size: 7.5pt; color: #94a3b8;">RM {{ $money($totals['topValue']) }} &middot; {{ $pct($totals['topShare']) }}</div>
            </td>
        </tr>
    </table>

    @if (empty($groups))
        <div style="border: 1px solid #e5e7eb; background: #f9fafb; padding: 28px; text-align: center; color: #64748b; font-size: 10pt; margin-top: 10px;">
            No {{ \Illuminate\Support\Str::plural($noun, 2) }} fall in this range under these filters.
        </div>
    @else
        {{-- ═══ Chart — value per group ══════════════════════════════════ --}}
        <div class="section-header">{{ $valueLabel }} per {{ strtolower($groupLabel) }}</div>

        <table style="width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 4px;">
            <thead>
                <tr>
                    <th style="width: 3%; text-align: left; padding: 0 6px 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">#</th>
                    <th style="width: 28%; text-align: left; padding: 0 10px 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">{{ $groupLabel }}</th>
                    <th style="text-align: left; padding: 0 6px 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Share</th>
                    <th style="width: 10%; text-align: right; padding: 0 6px 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">{{ ucfirst(\Illuminate\Support\Str::plural($noun, 2)) }}</th>
                    <th style="width: 16%; text-align: right; padding: 0 0 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">{{ $valueLabel }}</th>
                </tr>
            </thead>
            <tbody>
                @php $widest = max(0.0001, collect($groups)->max('share')); @endphp
                @foreach ($groups as $g)
                    <tr>
                        <td style="text-align: left; padding: 2.5px 6px 2.5px 0; font-size: 8pt; color: #94a3b8; vertical-align: middle;">{{ $g['rank'] }}</td>
                        <td style="padding: 2.5px 10px 2.5px 0; font-size: 8pt; color: #0f172a; vertical-align: middle;">
                            <a href="#{{ $g['anchor'] }}" style="color: #0f172a; text-decoration: none;">{{ $g['name'] }}</a>
                        </td>
                        <td style="padding: 2.5px 6px 2.5px 0; vertical-align: middle;">
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;"><tr>
                                <td style="width: {{ round(($g['share'] / $widest) * 100, 2) }}%; padding: 0;">
                                    <div style="height: 10px; background: {{ $g['color'] }};"></div>
                                </td>
                                <td style="padding: 0 0 0 5px; font-size: 7.5pt; color: #64748b; white-space: nowrap;">{{ $pct($g['share']) }}</td>
                            </tr></table>
                        </td>
                        <td style="text-align: right; padding: 2.5px 6px 2.5px 0; font-size: 8pt; color: #475569; vertical-align: middle;">{{ number_format($g['count']) }}</td>
                        <td style="text-align: right; padding: 2.5px 0; font-size: 8.5pt; color: #0f172a; font-weight: bold; vertical-align: middle;">{{ $money($g['value']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ═══ The table the chart is of ════════════════════════════════ --}}
        <div class="section-header">{{ $groupLabel }} summary</div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 24px;">#</th>
                    <th>{{ $groupLabel }}</th>
                    <th class="right" style="width: 70px;">{{ ucfirst(\Illuminate\Support\Str::plural($noun, 2)) }}</th>
                    <th class="right" style="width: 90px;">{{ $valueLabel }} (RM)</th>
                    <th class="right" style="width: 60px;">Share</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $g)
                    <tr>
                        <td>{{ $g['rank'] }}</td>
                        <td><a href="#{{ $g['anchor'] }}" style="color: #1f2937; text-decoration: none;">{{ $g['name'] }}</a></td>
                        <td class="right">{{ number_format($g['count']) }}</td>
                        <td class="right">{{ $money($g['value']) }}</td>
                        <td class="right">{{ $pct($g['share']) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="background: #1f2937; color: #fff; font-weight: bold; font-size: 9pt;">TOTAL</td>
                    <td class="right" style="background: #1f2937; color: #fff; font-weight: bold;">{{ number_format($totals['count']) }}</td>
                    <td class="right" style="background: #1f2937; color: #fff; font-weight: bold;">{{ $money($totals['value']) }}</td>
                    <td class="right" style="background: #1f2937; color: #fff; font-weight: bold;">100.0%</td>
                </tr>
            </tbody>
        </table>

        {{-- ═══ Per-group detail ═════════════════════════════════════════ --}}
        @if ($omittedDetails)
            <div style="border: 1px solid #e5e7eb; background: #f9fafb; padding: 10px 12px; font-size: 8.5pt; color: #475569; margin-top: 10px;">
                The per-{{ strtolower($groupLabel) }} listing is left out: {{ number_format($totals['count']) }} {{ \Illuminate\Support\Str::plural($noun, $totals['count']) }} fall in this range,
                more than this report will print. The totals above cover every one of them &mdash; narrow the dates or pick a
                {{ strtolower($groupLabel) }} to see the individual {{ \Illuminate\Support\Str::plural($noun, 2) }}.
            </div>
        @else
            @foreach ($detailBlocks as $block)
                @php $g = $block['group']; @endphp
                <div style="page-break-inside: avoid;">
                    <div id="{{ $g['anchor'] }}" style="margin: 14px 0 6px 0; padding: 5px 8px; border-left: 4px solid {{ $g['color'] }}; background: #f8fafc;">
                        <table style="width: 100%; border-collapse: collapse;"><tr>
                            <td style="font-size: 10pt; font-weight: bold; color: #0f172a;">{{ $g['name'] }}</td>
                            <td style="text-align: right; font-size: 8pt; color: #64748b;">
                                {{ number_format($g['count']) }} {{ \Illuminate\Support\Str::plural($noun, $g['count']) }}
                                &middot; RM {{ $money($g['value']) }}
                                &middot; {{ $pct($g['share']) }} of {{ strtolower($valueLabel) }}
                                &middot; <a href="#summary" style="color: #0b7677; text-decoration: none;">&uarr; summary</a>
                            </td>
                        </tr></table>
                    </div>

                    <table class="items" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                @foreach ($detailColumns as $col)
                                    <th @if (isset($col['width'])) style="width: {{ $col['width'] }}px;" @endif class="{{ $col['align'] ?? '' }}">{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($block['rows'] as $row)
                                <tr>
                                    @foreach ($detailColumns as $col)
                                        @php $v = $col['value']($row); @endphp
                                        <td class="{{ $col['align'] ?? '' }}">{{ ($col['type'] ?? 'text') === 'number' ? number_format((float) $v, 2) : $v }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($detailColumns) }}" style="text-align: center; color: #94a3b8;">None.</td></tr>
                            @endforelse
                            @if ($block['more'] > 0)
                                <tr>
                                    <td colspan="{{ count($detailColumns) }}" style="font-size: 8pt; color: #64748b; font-style: italic;">
                                        Most recent {{ $block['rows']->count() }} shown &middot;
                                        {{ number_format($block['more']) }} earlier {{ \Illuminate\Support\Str::plural($noun, $block['more']) }} not listed,
                                        all of them included in the RM {{ $money($g['value']) }} total above.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    @endif
@endsection
