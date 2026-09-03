@extends('pdf.layout')

@section('title', 'Purchases by Supplier')

{{--
    Every chart here is drawn with tables and coloured blocks, not with an
    <svg> or a chart image.

    dompdf lays out tables and background colours faithfully and does almost
    nothing else — no canvas, no JavaScript, no flexbox. A chart library would
    render as a blank rectangle. What a PDF *can* do is links, so the charts are
    made navigable instead of hoverable: every supplier bar and legend chip is a
    link to that supplier's own block further down, and each block links back.
--}}

@section('content')
    @php
        $money = fn ($v) => 'RM ' . number_format((float) $v, 2);
        $pct   = fn ($v) => number_format((float) $v, 1) . '%';

        // Below about 1.5% a ribbon segment is a sliver with no room for a
        // label, so the tail is drawn as one band rather than as confetti.
        $ribbon = collect($suppliers)->take(10)->values();
        $tail   = collect($suppliers)->skip(10);
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
            <div class="doc-title">Purchases by Supplier</div>
            <div class="doc-number">{{ \Illuminate\Support\Carbon::parse($scope['from'])->format('d M Y') }} &ndash; {{ \Illuminate\Support\Carbon::parse($scope['to'])->format('d M Y') }}</div>
            <div style="font-size: 8.5pt; color: #6b7280; margin-top: 2px;">{{ number_format($totals['days']) }} {{ \Illuminate\Support\Str::plural('day', $totals['days']) }}</div>
        </div>
    </div>

    {{-- What the reader is looking at. A summary with the filters left off
         invites the wrong conclusion from a correct number. --}}
    <table id="summary" style="width: 100%; border-collapse: collapse; margin-bottom: 12px; border: 1px solid #e5e7eb;">
        <tr>
            <td style="width: 13%; padding: 5px 10px; background: #f9fafb; font-size: 8pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Outlet</td>
            <td style="width: 37%; padding: 5px 10px; font-size: 9pt; color: #0f172a; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">{{ $scope['outlet'] }}</td>
            <td style="width: 13%; padding: 5px 10px; background: #f9fafb; font-size: 8pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">Department</td>
            <td style="width: 37%; padding: 5px 10px; font-size: 9pt; color: #0f172a; border-bottom: 1px solid #e5e7eb;">{{ $scope['department'] }}</td>
        </tr>
        <tr>
            <td style="padding: 5px 10px; background: #f9fafb; font-size: 8pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb;">Supplier</td>
            <td style="padding: 5px 10px; font-size: 9pt; color: #0f172a; border-right: 1px solid #e5e7eb;">{{ $scope['supplier'] }}</td>
            <td style="padding: 5px 10px; background: #f9fafb; font-size: 8pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #e5e7eb;">Search</td>
            <td style="padding: 5px 10px; font-size: 9pt; color: #0f172a;">{{ $scope['search'] !== '' ? '"' . $scope['search'] . '"' : '—' }}</td>
        </tr>
    </table>

    {{-- ═══ Stat cards ═══════════════════════════════════════════════════ --}}
    <table style="width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 5px;">
        <tr>
            {{-- The currency sits small beside the figure rather than inside it:
                 "RM 150,231.90" at headline size wraps in a quarter-width card,
                 and a total broken over two lines reads as two numbers. --}}
            <td style="width: 25%; border: 1px solid #e5e7eb; border-top: 2.5px solid #0b7677; padding: 7px 10px;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px;">Total spend</div>
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a; white-space: nowrap;"><span style="font-size: 8pt; color: #64748b;">RM</span> {{ number_format($totals['spend'], 2) }}</div>
                <div style="font-size: 7.5pt; color: #94a3b8;">{{ $money($totals['perDay']) }} per day</div>
            </td>
            <td style="width: 25%; border: 1px solid #e5e7eb; border-top: 2.5px solid #43bdb8; padding: 7px 10px;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px;">Purchases</div>
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a; white-space: nowrap;">{{ number_format($totals['purchases']) }}</div>
                <div style="font-size: 7.5pt; color: #94a3b8;">{{ $money($totals['average']) }} average</div>
            </td>
            <td style="width: 25%; border: 1px solid #e5e7eb; border-top: 2.5px solid #1d4ed8; padding: 7px 10px;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px;">Suppliers</div>
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a; white-space: nowrap;">{{ number_format($totals['suppliers']) }}</div>
                <div style="font-size: 7.5pt; color: #94a3b8;">top 3 = {{ $pct($totals['topThreeShare']) }} of spend</div>
            </td>
            <td style="width: 25%; border: 1px solid #e5e7eb; border-top: 2.5px solid #7c3aed; padding: 7px 10px;">
                <div style="font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px;">Biggest supplier</div>
                <div style="font-size: 10pt; font-weight: bold; color: #0f172a; line-height: 1.2;">{{ \Illuminate\Support\Str::limit($totals['topName'], 24) }}</div>
                <div style="font-size: 7.5pt; color: #94a3b8;">{{ $money($totals['topSpend']) }} &middot; {{ $pct($totals['topShare']) }}</div>
            </td>
        </tr>
    </table>

    @if (empty($suppliers))
        <div style="border: 1px solid #e5e7eb; background: #f9fafb; padding: 28px; text-align: center; color: #64748b; font-size: 10pt; margin-top: 10px;">
            No purchases were captured in this range under these filters.
        </div>
    @else
        {{-- ═══ Chart 1 — share of spend ═════════════════════════════════ --}}
        <div class="section-header">Share of spend</div>

        {{-- One 100%-wide row split by share. table-layout: fixed is what makes
             dompdf honour the percentage widths instead of sizing to content. --}}
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 6px;">
            <tr>
                @foreach ($ribbon as $s)
                    <td style="width: {{ max(0.6, round($s['share'], 2)) }}%; height: 24px; background: {{ $s['color'] }}; border-right: 1px solid #ffffff;">
                        <a href="#{{ $s['anchor'] }}" style="display: block; height: 24px; text-decoration: none;">&nbsp;</a>
                    </td>
                @endforeach
                @if ($tail->isNotEmpty())
                    <td style="width: {{ max(0.6, round($tail->sum('share'), 2)) }}%; height: 24px; background: #94a3b8;">&nbsp;</td>
                @endif
            </tr>
        </table>

        {{-- Legend. Four chips a row: dompdf has no flex wrap, so the columns
             are a table like everything else here. --}}
        @php $legend = $ribbon->chunk(4); @endphp
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
            @foreach ($legend as $chunk)
                <tr>
                    @foreach ($chunk as $s)
                        {{-- Name above, share below — always, even when both would
                             fit on one line. A chip that reflows when the name is
                             long gives the four columns four different heights. --}}
                        <td style="width: 25%; padding: 2px 6px 4px 0; font-size: 8pt; color: #334155; vertical-align: top;">
                            {{-- Auto layout, deliberately: under table-layout: fixed
                                 dompdf shares the width out by column count and the
                                 9px swatch takes half the chip. --}}
                            <table style="border-collapse: collapse;"><tr>
                                <td style="padding: 1px 5px 0 0; vertical-align: top;">
                                    <div style="width: 9px; height: 9px; background: {{ $s['color'] }};"></div>
                                </td>
                                <td style="vertical-align: top;">
                                    <a href="#{{ $s['anchor'] }}" style="color: #334155; text-decoration: none;">{{ \Illuminate\Support\Str::limit($s['name'], 20) }}</a>
                                    <div style="color: #94a3b8;">{{ $pct($s['share']) }}</div>
                                </td>
                            </tr></table>
                        </td>
                    @endforeach
                    @for ($i = $chunk->count(); $i < 4; $i++)
                        <td style="width: 25%;"></td>
                    @endfor
                </tr>
            @endforeach
            @if ($tail->isNotEmpty())
                <tr>
                    <td colspan="4" style="padding: 2px 0; font-size: 8pt; color: #64748b;">
                        <table style="border-collapse: collapse;"><tr>
                            <td style="width: 9px; padding: 0 5px 0 0; vertical-align: middle;"><div style="width: 9px; height: 9px; background: #94a3b8;"></div></td>
                            <td style="vertical-align: middle;">{{ $tail->count() }} other {{ \Illuminate\Support\Str::plural('supplier', $tail->count()) }} &middot; {{ $pct($tail->sum('share')) }}</td>
                        </tr></table>
                    </td>
                </tr>
            @endif
        </table>

        <div style="font-size: 7.5pt; color: #94a3b8; font-style: italic; margin-bottom: 4px;">
            Each band and every supplier name in this report is a link &mdash; click one to jump to that supplier's purchases.
        </div>

        {{-- ═══ Chart 2 — spend per supplier ═════════════════════════════ --}}
        <div class="section-header">Spend per supplier</div>

        <table style="width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 4px;">
            <thead>
                <tr>
                    <th style="width: 26px; text-align: right; padding: 0 6px 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">#</th>
                    <th style="width: 152px; text-align: left; padding: 0 6px 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Supplier</th>
                    <th style="text-align: left; padding: 0 6px 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Share of spend</th>
                    <th style="width: 34px; text-align: right; padding: 0 6px 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Buys</th>
                    <th style="width: 74px; text-align: right; padding: 0 0 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Spend</th>
                </tr>
            </thead>
            <tbody>
                @php $widest = max(0.0001, collect($suppliers)->max('share')); @endphp
                @foreach ($suppliers as $s)
                    <tr>
                        <td style="text-align: right; padding: 2.5px 6px 2.5px 0; font-size: 8pt; color: #94a3b8; vertical-align: middle;">{{ $s['rank'] }}</td>
                        <td style="padding: 2.5px 6px 2.5px 0; font-size: 8pt; color: #0f172a; vertical-align: middle;">
                            <a href="#{{ $s['anchor'] }}" style="color: #0f172a; text-decoration: none;">{{ \Illuminate\Support\Str::limit($s['name'], 22) }}</a>
                        </td>
                        <td style="padding: 2.5px 6px 2.5px 0; vertical-align: middle;">
                            {{-- The bar is scaled against the LARGEST supplier, not
                                 against 100%: on a list of thirty suppliers every
                                 bar would otherwise be a stub. The percentage is
                                 printed beside it so the scaling cannot mislead. --}}
                            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;"><tr>
                                <td style="width: {{ round(($s['share'] / $widest) * 100, 2) }}%; padding: 0;">
                                    <div style="height: 10px; background: {{ $s['color'] }};"></div>
                                </td>
                                <td style="padding: 0 0 0 5px; font-size: 7.5pt; color: #64748b; white-space: nowrap;">{{ $pct($s['share']) }}</td>
                            </tr></table>
                        </td>
                        <td style="text-align: right; padding: 2.5px 6px 2.5px 0; font-size: 8pt; color: #475569; vertical-align: middle;">{{ number_format($s['purchases']) }}</td>
                        <td style="text-align: right; padding: 2.5px 0; font-size: 8.5pt; color: #0f172a; font-weight: bold; vertical-align: middle;">{{ number_format($s['spend'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ═══ Chart 3 — when the money went out ════════════════════════ --}}
        @if (count($months) > 1)
            <div class="section-header">Spend by month</div>

            @php
                // Four months across a full page gives 190pt-wide columns, which
                // read as a stacked area rather than as a bar chart. Padding the
                // track out to at least eight slots keeps a bar bar-shaped; past
                // eight the columns are narrow enough to stand on their own.
                $slots   = max(8, count($months));
                $fillers = $slots - count($months);
            @endphp
            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                <tr>
                    @foreach ($months as $m)
                        {{-- vertical-align: bottom is what makes a column chart out
                             of table cells: the bar grows up from the axis. --}}
                        <td style="width: {{ round(100 / $slots, 3) }}%; height: 84px; vertical-align: bottom; padding: 0 5px;">
                            <div style="height: {{ max(1, round(($m['height'] / 100) * 78)) }}px; background: #0b7677;"></div>
                        </td>
                    @endforeach
                    @for ($i = 0; $i < $fillers; $i++)
                        <td style="width: {{ round(100 / $slots, 3) }}%;"></td>
                    @endfor
                </tr>
                <tr>
                    @foreach ($months as $m)
                        <td style="border-top: 1px solid #cbd5e1; padding: 3px 2px 0 2px; text-align: center; font-size: 7pt; color: #64748b;">
                            {{ $m['label'] }}
                        </td>
                    @endforeach
                    @for ($i = 0; $i < $fillers; $i++)
                        <td style="border-top: 1px solid #e2e8f0;"></td>
                    @endfor
                </tr>
                <tr>
                    @foreach ($months as $m)
                        <td style="padding: 1px 2px 0 2px; text-align: center; font-size: 7pt; color: #0f172a; font-weight: bold;">
                            {{ number_format($m['spend'], 0) }}
                        </td>
                    @endforeach
                    @for ($i = 0; $i < $fillers; $i++)
                        <td></td>
                    @endfor
                </tr>
            </table>
            <div style="font-size: 7pt; color: #94a3b8; font-style: italic; margin-top: 2px;">Figures in RM, rounded. Columns are scaled to the busiest month.</div>
        @endif

        {{-- ═══ The table the charts are of ══════════════════════════════ --}}
        <div class="section-header">Supplier summary</div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 24px;">#</th>
                    <th>Supplier</th>
                    <th class="right" style="width: 44px;">Buys</th>
                    <th class="right" style="width: 72px;">Spend</th>
                    <th class="right" style="width: 48px;">Share</th>
                    <th class="right" style="width: 66px;">Average</th>
                    <th class="center" style="width: 62px;">First</th>
                    <th class="center" style="width: 62px;">Last</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($suppliers as $s)
                    <tr>
                        <td>{{ $s['rank'] }}</td>
                        <td>
                            <a href="#{{ $s['anchor'] }}" style="color: #1f2937; text-decoration: none;">{{ $s['name'] }}</a>
                            @unless ($s['supplier_id'])
                                <span style="color: #94a3b8; font-size: 7.5pt;">&middot; unlinked</span>
                            @endunless
                        </td>
                        <td class="right">{{ number_format($s['purchases']) }}</td>
                        <td class="right">{{ number_format($s['spend'], 2) }}</td>
                        <td class="right">{{ $pct($s['share']) }}</td>
                        <td class="right">{{ number_format($s['average'], 2) }}</td>
                        <td class="center">{{ \Illuminate\Support\Carbon::parse($s['first_at'])->format('d/m/y') }}</td>
                        <td class="center">{{ \Illuminate\Support\Carbon::parse($s['last_at'])->format('d/m/y') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" style="background: #1f2937; color: #fff; font-weight: bold; font-size: 9pt;">TOTAL</td>
                    <td class="right" style="background: #1f2937; color: #fff; font-weight: bold;">{{ number_format($totals['purchases']) }}</td>
                    <td class="right" style="background: #1f2937; color: #fff; font-weight: bold;">{{ number_format($totals['spend'], 2) }}</td>
                    <td class="right" style="background: #1f2937; color: #fff; font-weight: bold;">100.0%</td>
                    <td class="right" style="background: #1f2937; color: #fff; font-weight: bold;">{{ number_format($totals['average'], 2) }}</td>
                    <td colspan="2" style="background: #1f2937;"></td>
                </tr>
            </tbody>
        </table>

        {{-- ═══ Per-supplier detail ══════════════════════════════════════ --}}
        @if ($details['omitted'])
            <div style="border: 1px solid #e5e7eb; background: #f9fafb; padding: 10px 12px; font-size: 8.5pt; color: #475569; margin-top: 10px;">
                The per-supplier listing is left out: {{ number_format($totals['purchases']) }} purchases fall in this range, more than
                this report will print. The totals above cover every one of them &mdash; narrow the dates or pick a
                department to see the individual purchases.
            </div>
        @else
            @foreach ($details['blocks'] as $block)
                @php $s = $block['supplier']; @endphp
                <div style="page-break-inside: avoid;">
                    <div id="{{ $s['anchor'] }}" style="margin: 14px 0 6px 0; padding: 5px 8px; border-left: 4px solid {{ $s['color'] }}; background: #f8fafc;">
                        <table style="width: 100%; border-collapse: collapse;"><tr>
                            <td style="font-size: 10pt; font-weight: bold; color: #0f172a;">{{ $s['name'] }}</td>
                            <td style="text-align: right; font-size: 8pt; color: #64748b;">
                                {{ number_format($s['purchases']) }} {{ \Illuminate\Support\Str::plural('purchase', $s['purchases']) }}
                                &middot; {{ $money($s['spend']) }}
                                &middot; {{ $pct($s['share']) }} of spend
                                &middot; <a href="#summary" style="color: #0b7677; text-decoration: none;">&uarr; summary</a>
                            </td>
                        </tr></table>
                    </div>

                    <table class="items" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                {{-- Wide enough for "02 Aug 2026" on one line. At
                                     66px it wrapped, which doubled the height of
                                     every row in the report. --}}
                                <th style="width: 82px;">Date</th>
                                <th style="width: 108px;">Reference</th>
                                <th>Department</th>
                                <th>Outlet</th>
                                <th class="right" style="width: 78px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($block['rows'] as $row)
                                <tr>
                                    <td style="white-space: nowrap;">{{ $row->purchase_date?->format('d M Y') ?? '—' }}</td>
                                    <td>{{ $row->reference_number ?: '—' }}</td>
                                    <td>{{ $row->department?->name ?? '—' }}</td>
                                    <td>{{ $row->outlet?->name ?? '—' }}</td>
                                    <td class="right">{{ number_format((float) $row->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="text-align: center; color: #94a3b8;">No purchases.</td></tr>
                            @endforelse
                            @if ($block['more'] > 0)
                                <tr>
                                    <td colspan="5" style="font-size: 8pt; color: #64748b; font-style: italic;">
                                        Most recent {{ $block['rows']->count() }} shown &middot;
                                        {{ number_format($block['more']) }} earlier {{ \Illuminate\Support\Str::plural('purchase', $block['more']) }} not listed,
                                        all of them included in the {{ $money($s['spend']) }} total above.
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
