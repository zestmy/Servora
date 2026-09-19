@extends('pdf.layout')

@section('title', ($report['granularity'] === 'month' ? 'Monthly' : 'Weekly') . ' WIP Review')

{{--
    The whole WIP review for the meeting pack: every slide of the on-screen deck,
    in the same order, from the same service output. Charts are coloured bars in
    tables — dompdf has no canvas. Pay figures are only present in $report when
    the viewer may see pay (see WeeklyWipReview::build), so nothing here has to
    remember to hide them.
--}}

@section('content')
    @php
        $monthly = $report['granularity'] === 'month';
        $unit    = $monthly ? 'month' : 'week';
        $vsLast  = $monthly ? 'vs last mth' : 'vs last wk';
        $canPay  = $report['can_view_pay'];
        $labour  = $report['labour'];
        $colors  = $report['charts']['trend']['colors'];

        $num   = fn ($v, int $dp = 2) => $v === null ? '—' : number_format((float) $v, $dp);
        $money = fn ($v) => $v === null ? '—' : 'RM ' . number_format((float) $v, 2);
        $pct   = fn ($v) => $v === null ? '—' : number_format((float) $v, 1) . '%';
        $fmt   = fn ($v, string $f) => match ($f) {
            'pct'   => $pct($v),
            'hours' => $v === null ? '—' : number_format((float) $v, 1) . ' h',
            default => $money($v),
        };

        // The screen's rule: coloured by whether it is good news, not by sign.
        $delta = function ($change, ?bool $upIsGood, bool $points = false): array {
            if ($change === null) {
                return ['—', '#94a3b8'];
            }
            if (abs($change) < 0.05) {
                return [$points ? '0.0 pts' : '0.0%', '#64748b'];
            }
            $text = ($change > 0 ? '▲ ' : '▼ ') . number_format(abs($change), 1) . ($points ? ' pts' : '%');
            if ($upIsGood === null) {
                return [$text, '#475569'];
            }

            return [$text, ($upIsGood ? $change > 0 : $change < 0) ? '#15803d' : '#dc2626'];
        };

        $bar = function (float $value, float $peak, string $color, int $height = 7): string {
            $w = $peak > 0 ? round(max(0, $value) / $peak * 100, 2) : 0;

            return $w <= 0
                ? '<div style="height: ' . $height . 'px;"></div>'
                : '<div style="height: ' . $height . 'px; width: ' . $w . '%; background: ' . e($color) . ';"></div>';
        };

        $cw = $report['current'];
        $pw = $report['previous'];
        $th = 'padding: 0 6px 4px 0; font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;';
        $note = 'font-size: 7.5pt; color: #64748b; margin-top: 3px;';
    @endphp

    {{-- ═══ Header ═══════════════════════════════════════════════════════ --}}
    <div class="header">
        <div class="header-left">
            @if ($company?->logo)
                <img src="{{ public_path('storage/' . $company->logo) }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $company?->name ?? 'Company' }}</div>
        </div>
        <div class="header-right">
            <div class="doc-title">{{ $monthly ? 'Monthly' : 'Weekly' }} WIP Review</div>
            <div class="doc-number">{{ $cw['range'] }}</div>
            <div style="font-size: 8.5pt; color: #6b7280; margin-top: 2px;">compared with {{ $pw['range'] }}</div>
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb;">
        <tr>
            <td style="width: 12%; padding: 5px 10px; background: #f9fafb; font-size: 7.5pt; font-weight: bold; color: #475569; text-transform: uppercase;">Outlets</td>
            <td style="width: 21%; padding: 5px 10px; font-size: 9pt;">{{ $scopeLabel }}</td>
            <td style="width: 12%; padding: 5px 10px; background: #f9fafb; font-size: 7.5pt; font-weight: bold; color: #475569; text-transform: uppercase;">Trend</td>
            <td style="width: 21%; padding: 5px 10px; font-size: 9pt;">{{ count($report['periods']) }} {{ \Illuminate\Support\Str::plural($unit, count($report['periods'])) }}</td>
            <td style="width: 12%; padding: 5px 10px; background: #f9fafb; font-size: 7.5pt; font-weight: bold; color: #475569; text-transform: uppercase;">Payroll</td>
            <td style="padding: 5px 10px; font-size: 9pt;">
                @if (! $canPay)
                    Pay figures left out (needs compensation access)
                @elseif (! $monthly)
                    Monthly view only
                @else
                    {{ $labour['include_drafts'] ? 'Approved, paid and draft runs' : 'Approved and paid runs' }}
                @endif
            </td>
        </tr>
    </table>

    {{-- ═══ At a glance ══════════════════════════════════════════════════ --}}
    <div class="section-header">{{ $monthly ? 'Month' : 'Week' }} at a glance</div>

    @if (! $report['has_data'])
        <div style="border: 1px solid #e5e7eb; background: #f9fafb; padding: 20px; text-align: center; color: #64748b; font-size: 10pt;">
            Nothing recorded for these {{ $unit }}s.
        </div>
    @else
        <table style="width: 100%; border-collapse: separate; border-spacing: 4px 4px;">
            @foreach (array_chunk($report['kpis'], 4) as $row)
                <tr>
                    @foreach ($row as $k)
                        @php [$dt, $dc] = $delta($k['change'], $k['up_is_good'], $k['format'] === 'pct'); @endphp
                        <td style="width: 25%; border: 1px solid #e5e7eb; border-top: 2.5px solid {{ $colors['sales'] }}; padding: 5px 8px; vertical-align: top;">
                            <div style="font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">{{ $k['label'] }}</div>
                            <div style="font-size: 12pt; font-weight: bold; color: #0f172a; white-space: nowrap;">{{ $fmt($k['current'], $k['format']) }}</div>
                            <div style="font-size: 7.5pt;">
                                <span style="color: {{ $dc }}; font-weight: bold;">{{ $dt }}</span>
                                <span style="color: #94a3b8;">vs {{ $fmt($k['previous'], $k['format']) }}</span>
                            </div>
                            @if ($k['share'] !== null)
                                @php [$st, $sc] = $delta($k['share']['change'], $k['up_is_good'], true); @endphp
                                <div style="font-size: 7.5pt; border-top: 1px solid #f1f5f9; margin-top: 2px; padding-top: 1px;">
                                    <span style="color: #0f172a; font-weight: bold;">{{ $pct($k['share']['current']) }}</span>
                                    <span style="color: #64748b;">of sales</span>
                                    <span style="color: {{ $sc }}; font-weight: bold;">{{ $st }}</span>
                                </div>
                            @endif
                        </td>
                    @endforeach
                    @for ($i = count($row); $i < 4; $i++)
                        <td style="width: 25%;"></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @endif

    {{-- ═══ Sales performance (weekly) ═══════════════════════════════════ --}}
    @if ($report['sales_performance'] !== null)
        @php
            $sp      = $report['sales_performance'];
            $varRows = array_merge($sp['variance']['days'], [$sp['variance']['total']]);
            $dark    = 'background: #1f2937; color: #fff; font-weight: bold;';
            $signed  = function (float $amount) {
                $up = $amount > 0.005;
                $down = $amount < -0.005;

                return [($up ? '▲ ' : ($down ? '▼ ' : '')) . number_format(abs($amount), 2), $up ? '#15803d' : ($down ? '#dc2626' : '#64748b')];
            };
        @endphp
        <div style="page-break-before: always;"></div>
        <div class="section-header">Sales performance</div>
        <table class="items">
            <thead>
                <tr>
                    <th>Day</th>
                    @foreach ($sp['day_names'] as $dn)
                        <th class="right">{{ $dn }}</th>
                    @endforeach
                    <th class="right">Total</th>
                    <th class="right">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach (['current', 'previous'] as $which)
                    @php $wk = $sp[$which]; @endphp
                    @foreach ($wk['lines'] as $l)
                        <tr>
                            <td style="font-weight: bold;">{{ $l['label'] }}</td>
                            @foreach ($l['days'] as $v)
                                <td class="right">{{ $num($v) }}</td>
                            @endforeach
                            <td class="right" style="font-weight: bold;">{{ $num($l['total']) }}</td>
                            <td class="right" style="font-style: italic; color: #64748b;">{{ $pct($l['share']) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="{{ $dark }}">{{ strtoupper($wk['label']) }} <span style="font-weight: normal; font-size: 7pt;">{{ $wk['range'] }}</span></td>
                        @foreach ($wk['days'] as $v)
                            <td class="right" style="{{ $dark }}">{{ $num($v) }}</td>
                        @endforeach
                        <td class="right" style="{{ $dark }}">{{ $num($wk['total']) }}</td>
                        <td style="{{ $dark }}"></td>
                    </tr>
                @endforeach
                <tr>
                    <td style="font-weight: bold;">VAR. RM</td>
                    @foreach ($varRows as $v)
                        @php [$vt, $vc] = $signed($v['amount']); @endphp
                        <td class="right" style="font-weight: bold; color: {{ $vc }};">{{ $vt }}</td>
                    @endforeach
                    <td></td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">VAR. %</td>
                    @foreach ($varRows as $v)
                        @php [$vt, $vc] = $delta($v['change'], true); @endphp
                        <td class="right" style="font-weight: bold; color: {{ $vc }};">{{ $vt }}</td>
                    @endforeach
                    <td></td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- ═══ Sales by category ════════════════════════════════════════════ --}}
    @php $cat = $report['categories']; @endphp
    @if ($cat['rows'] !== [])
        <div style="page-break-before: always;"></div>
        <div class="section-header">{{ $monthly ? 'Monthly' : 'Weekly' }} sales by category</div>
        <table class="items">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="right">{{ $cw['label'] }}</th>
                    <th class="right">{{ $pw['label'] }}</th>
                    <th class="right">Variance (RM)</th>
                    <th class="right">Var. %</th>
                </tr>
            </thead>
            <tbody>
                @foreach (array_merge($cat['rows'], [$cat['total']]) as $r)
                    @php
                        $bold = $loop->last ? 'font-weight: bold; border-top: 1px solid #1f2937;' : '';
                        $up = $r['variance'] > 0.005;
                        $down = $r['variance'] < -0.005;
                        [$vt, $vc] = $delta($r['change'], true);
                        $amt = fn ($v) => abs($v) < 0.005 ? '–' : number_format($v, 2);
                    @endphp
                    <tr>
                        <td style="font-weight: bold; text-transform: uppercase; {{ $bold }}">{{ $r['name'] }}</td>
                        <td class="right" style="{{ $bold }}">{{ $amt($r['current']) }}</td>
                        <td class="right" style="{{ $bold }}">{{ $amt($r['previous']) }}</td>
                        <td class="right" style="font-weight: bold; color: {{ $up ? '#15803d' : ($down ? '#dc2626' : '#64748b') }}; {{ $bold }}">
                            {{ $up || $down ? ($up ? '▲ ' : '▼ ') . number_format(abs($r['variance']), 2) : 'NIL' }}
                        </td>
                        <td class="right" style="font-weight: bold; color: {{ $vc }}; {{ $bold }}">{{ $vt }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="{{ $note }}">
            Sales category as keyed on each sales line. Uncategorised is revenue recorded as a total with no lines behind it.
        </div>
    @endif

    @if ($report['sales_performance'] !== null)
        <div style="page-break-before: always;"></div>
        <div class="section-header">Month to date — sales, covers &amp; average check</div>
        <table class="items">
            <thead>
                <tr>
                    <th>Period</th><th>Dates</th>
                    <th class="right">Sales</th><th class="right">Covers</th><th class="right">Avg check</th>
                    <th class="right">Sales vs this month</th><th class="right">Covers vs this month</th><th class="right">Variance (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sp['mtd'] as $m)
                    <tr>
                        <td style="font-weight: bold;">{{ $m['label'] }}</td>
                        <td>{{ $m['range'] }}</td>
                        <td class="right">{{ $num($m['sales']) }}</td>
                        <td class="right">{{ number_format($m['covers']) }}</td>
                        <td class="right">{{ $num($m['avg_check']) }}</td>
                        @if ($m['key'] === 'this_month')
                            <td></td><td></td><td></td>
                        @else
                            @php
                                [$st, $sc] = $delta($m['sales_change'], true);
                                [$ct, $cc] = $delta($m['covers_change'], true);
                                [$vt, $vc] = $signed($m['variance']);
                            @endphp
                            <td class="right" style="font-weight: bold; color: {{ $sc }};">{{ $st }}</td>
                            <td class="right" style="font-weight: bold; color: {{ $cc }};">{{ $ct }}</td>
                            <td class="right" style="font-weight: bold; color: {{ $vc }};">{{ $vt }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="{{ $note }}">
            Meal period as keyed on each sales record (none = All Day). Covers are the pax recorded with sales. Each
            month-to-date comparison stops on the same day of its own month.
        </div>

        @php
            $fc = $sp['forecast'];
            $paren = fn ($v) => $v === null ? '—' : ($v < 0 ? '(' . number_format(abs($v), 2) . ')' : number_format($v, 2));
            $forecastRows = [
                ['Days in month', number_format($fc['days_in_month']), ''],
                ['Month to date (days)', number_format($fc['mtd_days']), ''],
                ['No. of days left', number_format($fc['days_left']), ''],
                ['Monthly target', $fc['target'] === null ? 'Not set' : number_format($fc['target'], 2), ''],
                ['Month-to-date sales', number_format($fc['mtd_sales'], 2), ''],
                ['Balance to reach target', $paren($fc['balance']), $fc['balance'] !== null && $fc['balance'] < 0 ? 'color: #dc2626;' : ''],
                ['Forecast avg daily sales', number_format($fc['avg_daily'], 2), ''],
                ['Forecast sales for remaining days', number_format($fc['remaining'], 2), ''],
                ['Forecast monthly sales', number_format($fc['forecast'], 2), 'font-weight: bold; font-size: 10pt;'],
                ['Forecast vs target', $pct($fc['forecast_vs_target']),
                    $fc['forecast_vs_target'] === null ? '' : ($fc['forecast_vs_target'] >= 100 ? 'color: #15803d; font-weight: bold;' : 'color: #dc2626; font-weight: bold;')],
                ['Needed per day to reach target', $fc['needed_daily'] === null ? '—' : number_format($fc['needed_daily'], 2), ''],
            ];
        @endphp
        <div style="page-break-inside: avoid;">
            <div class="section-header">Sales forecast — {{ $fc['month_label'] }}</div>
            <table class="items" style="width: 50%;">
                <tbody>
                    @foreach ($forecastRows as [$label, $value, $style])
                        <tr>
                            <td style="background: #1f2937; color: #fff; font-weight: bold; text-transform: uppercase; font-size: 8pt;">{{ $label }}</td>
                            <td class="right" style="{{ $style }}">{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="{{ $note }}">
                Forecast = month-to-date sales plus the month-to-date daily average over the days left.
                @switch($fc['target_source'])
                    @case('outlet') Target is this outlet's, from Settings &gt; Sales Targets. @break
                    @case('company') Target is the company-wide one from Settings &gt; Sales Targets. @break
                    @case('outlets') Target adds up {{ $fc['target_count'] }} outlet target(s) — no company-wide target is set. @break
                    @default No sales target is set for {{ $fc['month_label'] }}.
                @endswitch
            </div>
        </div>
    @endif

    {{-- ═══ Cost of goods % (monthly) — in place of Purchase cost % ═══════ --}}
    @php $cs = $report['cost_summary'] ?? null; @endphp
    @if ($cs !== null)
        @php
            $ct = $cs['trend'];
            $cogsPeak = max(0.01, ...array_map(fn ($v) => (float) $v, $ct['cogs_pct']));
        @endphp
        <div style="page-break-inside: avoid;">
            <div class="section-header">Cost of goods %</div>
            <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                <thead>
                    <tr>
                        <th style="width: 13%; text-align: left; {{ $th }}">Month</th>
                        <th style="text-align: left; {{ $th }}">
                            <span style="color: {{ $colors['purchases'] }};">■</span> Cost of goods % of sales
                            &nbsp; <span style="color: {{ $colors['previous'] }};">■</span> no stock take at one end (purchases only)
                        </th>
                        <th style="width: 13%; text-align: right; {{ $th }}">Sales</th>
                        <th style="width: 13%; text-align: right; {{ $th }}">Cost of goods</th>
                        <th style="width: 9%; text-align: right; {{ $th }}">Cost %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ct['labels'] as $i => $label)
                        <tr>
                            <td style="font-size: 8pt; padding: 2px 6px 2px 0; {{ $loop->last ? 'font-weight: bold;' : '' }}">{{ $label }}</td>
                            <td style="padding: 2px 6px 2px 0;">
                                {!! $bar((float) $ct['cogs_pct'][$i], $cogsPeak, $ct['complete'][$i] ? $colors['purchases'] : $colors['previous'], 12) !!}
                            </td>
                            <td style="text-align: right; font-size: 8pt; padding: 2px 6px 2px 0;">{{ $num($ct['revenue'][$i]) }}</td>
                            <td style="text-align: right; font-size: 8pt; padding: 2px 6px 2px 0;">{{ $num($ct['cogs'][$i]) }}</td>
                            <td style="text-align: right; font-size: 8pt; padding: 2px 0;">{{ $pct($ct['cogs_pct'][$i]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($cs['rows'] !== [])
                @php $csTransfers = collect($cs['rows'])->contains(fn ($r) => abs($r['transfer_in']) > 0.005 || abs($r['transfer_out']) > 0.005); @endphp
                <table class="items" style="margin-top: 8px;">
                    <thead>
                        <tr>
                            <th>{{ $cs['month_label'] }}</th>
                            <th class="right">Revenue</th>
                            <th class="right">Opening stock</th>
                            <th class="right">+ Purchases</th>
                            @if ($csTransfers)<th class="right">± Transfers</th>@endif
                            <th class="right">− Closing stock</th>
                            <th class="right">= Cost of goods</th>
                            <th class="right">Cost %</th>
                            <th class="right">{{ $vsLast }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_merge($cs['rows'], [$cs['total'] + ['name' => 'Total']]) as $r)
                            @php
                                $b = $loop->last ? 'font-weight: bold; border-top: 1px solid #1f2937;' : '';
                                [$vt, $vc] = $delta($r['cost_pct_change'], false, true);
                                $net = $r['transfer_in'] - $r['transfer_out'];
                            @endphp
                            <tr>
                                <td style="font-weight: bold; {{ $b }}">{{ $r['name'] }}@if (($r['basis'] ?? null) === 'total_sales')<div style="font-size: 7pt; font-weight: normal; color: #64748b;">measured against total sales</div>@endif</td>
                                <td class="right" style="{{ $b }}">{{ $num($r['revenue']) }}</td>
                                <td class="right" style="{{ $b }}">{{ $num($r['opening_stock']) }}</td>
                                <td class="right" style="{{ $b }}">{{ $num($r['purchases']) }}</td>
                                @if ($csTransfers)<td class="right" style="{{ $b }}">{{ $net < 0 ? '(' . $num(abs($net)) . ')' : $num($net) }}</td>@endif
                                <td class="right" style="{{ $b }}">{{ $num($r['closing_stock']) }}</td>
                                <td class="right" style="font-weight: bold; {{ $b }}">{{ $num($r['cogs']) }}</td>
                                <td class="right" style="font-weight: bold; {{ $b }}">{{ $r['revenue'] > 0 ? $pct($r['cost_pct']) : '—' }}</td>
                                <td class="right" style="color: {{ $vc }}; font-size: 8pt; {{ $b }}">{{ $vt }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <div style="{{ $note }}">
                Cost of goods = opening stock + purchases{{ $csTransfers ?? false ? ' ± transfers' : '' }} − closing stock, as on Reports &gt; Cost Summary.
                @if (! $cs['has_opening'] && ! $cs['has_closing'])
                    No completed stock take for {{ $cs['previous_label'] }} or {{ $cs['month_label'] }} — both count as 0, so {{ $cs['month_label'] }} is purchases only.
                @elseif (! $cs['has_opening'])
                    No completed stock take for {{ $cs['previous_label'] }} — opening stock counts as 0.
                @elseif (! $cs['has_closing'])
                    No completed stock take for {{ $cs['month_label'] }} yet — closing stock counts as 0.
                @endif
                {{ $cs['company_wide'] ? 'Company-wide.' : '' }}
            </div>
        </div>
    @else
    {{-- ═══ Purchase cost % ══════════════════════════════════════════════ --}}
    <div style="page-break-inside: avoid;">
        <div class="section-header">Purchase cost %</div>
        @php $peak = max(0.01, ...array_map('floatval', $report['totals']['cost_pct'])); @endphp
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
            <thead>
                <tr>
                    <th style="width: 13%; text-align: left; {{ $th }}">{{ ucfirst($unit) }}</th>
                    <th style="text-align: left; {{ $th }}">
                        <span style="color: {{ $colors['purchases'] }};">■</span> Purchase cost % of sales
                    </th>
                    <th style="width: 13%; text-align: right; {{ $th }}">Sales</th>
                    <th style="width: 13%; text-align: right; {{ $th }}">Purchases</th>
                    <th style="width: 9%; text-align: right; {{ $th }}">Cost %</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['periods'] as $i => $p)
                    <tr>
                        <td style="font-size: 8pt; padding: 2px 6px 2px 0; {{ $loop->last ? 'font-weight: bold;' : '' }}">{{ $p['label'] }}</td>
                        <td style="padding: 2px 6px 2px 0;">
                            {!! $bar((float) $report['totals']['cost_pct'][$i], $peak, $colors['purchases'], 12) !!}
                        </td>
                        <td style="text-align: right; font-size: 8pt; padding: 2px 6px 2px 0;">{{ $num($report['totals']['sales'][$i]) }}</td>
                        <td style="text-align: right; font-size: 8pt; padding: 2px 6px 2px 0;">{{ $num($report['totals']['purchases'][$i]) }}</td>
                        <td style="text-align: right; font-size: 8pt; padding: 2px 0;">{{ $pct($report['totals']['cost_pct'][$i]) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @php
        // [label, total key, format, % of sales key shown under the amount]
        $trendRows = [
            ['Sales', 'sales', 'money', null],
            ['Purchases', 'purchases', 'money', 'cost_pct'],
            ['Wastage', 'wastage', 'money', 'wastage_pct'],
            ['Staff meals', 'staff_meal', 'money', 'staff_meal_pct'],
            ['Stock transfers', 'transfers', 'money', null],
            ['OT hours (approved)', 'ot_hours', 'hours', null],
        ];
        if ($canPay) {
            $trendRows[] = ['OT cost (estimated)', 'ot_cost', 'money', 'ot_cost_pct'];
        }
        if ($labour !== null) {
            $trendRows[] = ['Labour cost', 'labour_cost', 'money', 'labour_pct'];
        }
        // A figure that is zero in every period shown gets no row. Sales always stays.
        $trendRows = array_values(array_filter($trendRows, fn ($r) => $r[1] === 'sales'
            || collect($report['totals'][$r[1]])->contains(fn ($v) => abs((float) $v) > 0.005)));
    @endphp
    <table class="items" style="margin-top: 8px; page-break-inside: avoid;">
        <thead>
            <tr>
                <th>{{ ucfirst($unit) }}</th>
                @foreach ($report['periods'] as $p)
                    <th class="right">{{ $p['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($trendRows as [$label, $key, $format, $pctKey])
                <tr>
                    <td style="font-weight: bold;">
                        {{ $label }}
                        @if ($pctKey)
                            <div style="font-size: 7pt; color: #64748b; font-weight: normal;">RM · % of sales</div>
                        @endif
                    </td>
                    @foreach ($report['totals'][$key] as $i => $v)
                        <td class="right" style="{{ $loop->last ? 'font-weight: bold;' : '' }}">
                            {{ $format === 'hours' ? $num($v, 1) : $num($v) }}
                            @if ($pctKey)
                                <div style="font-size: 7pt; color: #64748b; font-weight: normal;">{{ $pct($report['totals'][$pctKey][$i]) }}</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ═══ Purchases by department ══════════════════════════════════════ --}}
    {{-- Purchases and wastage by department are separate sections, as on the
         slides: one table carrying both ran to ten columns. --}}
    <div style="page-break-before: always;"></div>
    <div class="section-header">Purchases by department</div>
    @if ($report['departments'] === [])
        <div style="{{ $note }}">No department figures for these two {{ $unit }}s.</div>
    @else
        @php $costPeak = max(0.01, ...array_map(fn ($d) => max((float) $d['cost_pct']['current'], (float) $d['cost_pct']['previous']), $report['departments'])); @endphp
        <table class="items">
            <thead>
                <tr>
                    <th>Department</th>
                    <th style="width: 22%;">
                        <span style="color: {{ $colors['purchases'] }};">■</span> % this {{ $unit }}
                        &nbsp; <span style="color: {{ $colors['previous'] }};">■</span> Last
                    </th>
                    <th class="right">Sales</th><th class="right">{{ $vsLast }}</th>
                    <th class="right">Purchases</th><th class="right">{{ $vsLast }}</th>
                    <th class="right">Cost %</th><th class="right">{{ $vsLast }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach (array_filter($report['departments'], fn ($d) => $d['purchases']['current'] > 0 || $d['purchases']['previous'] > 0) as $d)
                    @php
                        $s = $delta($d['sales']['change'], true);
                        $p = $delta($d['purchases']['change'], false);
                        $c = $delta($d['cost_pct']['change'], false, true);
                    @endphp
                    <tr>
                        <td style="font-weight: bold;">{{ $d['name'] }}@if (! empty($d['shared_with']))<div style="font-size: 7pt; font-weight: normal; color: #64748b;">sales shared with {{ implode(', ', $d['shared_with']) }}</div>@endif@if (! empty($d['total_sales']))<div style="font-size: 7pt; font-weight: normal; color: #64748b;">vs total sales</div>@endif</td>
                        <td>
                            {!! $bar((float) $d['cost_pct']['current'], $costPeak, $colors['purchases']) !!}
                            <div style="height: 1px;"></div>
                            {!! $bar((float) $d['cost_pct']['previous'], $costPeak, $colors['previous'], 4) !!}
                        </td>
                        <td class="right">{{ $num($d['sales']['current']) }}</td>
                        <td class="right" style="color: {{ $s[1] }}; font-size: 8pt;">{{ $s[0] }}</td>
                        <td class="right">{{ $num($d['purchases']['current']) }}</td>
                        <td class="right" style="color: {{ $p[1] }}; font-size: 8pt;">{{ $p[0] }}</td>
                        <td class="right" style="font-weight: bold;">{{ $pct($d['cost_pct']['current']) }}</td>
                        <td class="right" style="color: {{ $c[1] }}; font-size: 8pt;">{{ $c[0] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="{{ $note }}">
            Bars are purchase cost as % of each department's own sales. Sales reach a department through its sales
            category. Sales no department claims, and purchases keyed without a department, are shown as Unassigned.
            Departments sharing a sales category are each measured against all of its sales, so department sales can
            add up to more than total sales.
        </div>
    @endif

    {{-- ═══ Wastage, staff meals, transfers ══════════════════════════════ --}}
    @php
        $mealRows = array_values(array_filter($report['outlets'], fn ($o) => $o['staff_meal']['current'] > 0 || $o['staff_meal']['previous'] > 0));
        $transferRows = array_values(array_filter($report['outlets'], fn ($o) =>
            $o['transfers_out']['current'] > 0 || $o['transfers_in']['current'] > 0
            || $o['transfers_out']['previous'] > 0 || $o['transfers_in']['previous'] > 0));
    @endphp
    <table style="width: 100%; border-collapse: collapse; margin-top: 6px;">
        <tr>
            <td style="width: 32%; vertical-align: top; padding-right: 10px;">
                <div class="section-header">Wastage</div>
                <table class="items">
                    <thead><tr><th>{{ ucfirst($unit) }}</th><th class="right">Wastage</th><th class="right">% of sales</th></tr></thead>
                    <tbody>
                        @foreach ($report['periods'] as $i => $p)
                            <tr>
                                <td>{{ $p['label'] }}</td>
                                <td class="right">{{ $num($report['totals']['wastage'][$i]) }}</td>
                                <td class="right">{{ $pct($report['totals']['wastage_pct'][$i]) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td style="width: 32%; vertical-align: top; padding-right: 10px;">
                <div class="section-header">Staff meals</div>
                @if ($mealRows === [])
                    <div style="{{ $note }}">No staff meals in these two {{ $unit }}s.</div>
                @else
                    <table class="items">
                        <thead><tr><th>Outlet</th><th class="right">This {{ $unit }}</th><th class="right">Last</th><th class="right">Change</th></tr></thead>
                        <tbody>
                            @foreach ($mealRows as $o)
                                @php $m = $delta($o['staff_meal']['change'], false); @endphp
                                <tr>
                                    <td>{{ $o['name'] }}</td>
                                    <td class="right">{{ $num($o['staff_meal']['current']) }}<div style="font-size: 7pt; color: #64748b;">{{ $pct($o['staff_meal_pct']['current']) }}</div></td>
                                    <td class="right">{{ $num($o['staff_meal']['previous']) }}</td>
                                    <td class="right" style="color: {{ $m[1] }}; font-size: 8pt;">{{ $m[0] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
            <td style="vertical-align: top;">
                <div class="section-header">Stock transfers</div>
                @if ($transferRows === [])
                    <div style="{{ $note }}">No transfers in these two {{ $unit }}s.</div>
                @else
                    <table class="items">
                        <thead><tr><th>Outlet</th><th class="right">Sent</th><th class="right">Received</th><th class="right">Net</th></tr></thead>
                        <tbody>
                            @foreach ($transferRows as $o)
                                @php $net = $o['transfers_in']['current'] - $o['transfers_out']['current']; @endphp
                                <tr>
                                    <td>{{ $o['name'] }}</td>
                                    <td class="right">{{ $num($o['transfers_out']['current']) }}</td>
                                    <td class="right">{{ $num($o['transfers_in']['current']) }}</td>
                                    <td class="right" style="font-weight: bold;">{{ $net < 0 ? '(' . $num(abs($net)) . ')' : $num($net) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div style="{{ $note }}">In transit and received, at line cost.</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ═══ Wastage by department ════════════════════════════════════════ --}}
    @php $wasteRows = array_values(array_filter($report['departments'], fn ($d) => $d['wastage']['current'] > 0 || $d['wastage']['previous'] > 0)); @endphp
    @if ($wasteRows !== [])
        @php $wastePeak = max(0.01, ...array_map(fn ($d) => max((float) $d['wastage_pct']['current'], (float) $d['wastage_pct']['previous']), $wasteRows)); @endphp
        <div style="page-break-inside: avoid;">
            <div class="section-header">Wastage by department</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th style="width: 22%;">
                            <span style="color: {{ $colors['wastage'] }};">■</span> % this {{ $unit }}
                            &nbsp; <span style="color: {{ $colors['previous'] }};">■</span> Last
                        </th>
                        <th class="right">This {{ $unit }}</th>
                        <th class="right">Last {{ $unit }}</th>
                        <th class="right">Change</th>
                        <th class="right">% of dept sales</th>
                        <th class="right">Last {{ $unit }} %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($wasteRows as $d)
                        @php $w = $delta($d['wastage']['change'], false); @endphp
                        <tr>
                            <td style="font-weight: bold;">{{ $d['name'] }}@if (! empty($d['shared_with']))<div style="font-size: 7pt; font-weight: normal; color: #64748b;">sales shared with {{ implode(', ', $d['shared_with']) }}</div>@endif@if (! empty($d['total_sales']))<div style="font-size: 7pt; font-weight: normal; color: #64748b;">vs total sales</div>@endif</td>
                            <td>
                                {!! $bar((float) $d['wastage_pct']['current'], $wastePeak, $colors['wastage']) !!}
                                <div style="height: 1px;"></div>
                                {!! $bar((float) $d['wastage_pct']['previous'], $wastePeak, $colors['previous'], 4) !!}
                            </td>
                            <td class="right">{{ $num($d['wastage']['current']) }}</td>
                            <td class="right">{{ $num($d['wastage']['previous']) }}</td>
                            <td class="right" style="color: {{ $w[1] }}; font-size: 8pt;">{{ $w[0] }}</td>
                            <td class="right">{{ $pct($d['wastage_pct']['current']) }}</td>
                            <td class="right">{{ $pct($d['wastage_pct']['previous']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="{{ $note }}">Bars are wastage as % of each department's own sales.</div>
        </div>
    @endif

    {{-- ═══ Overtime claims ══════════════════════════════════════════════ --}}
    <div style="page-break-before: always;"></div>
    <div class="section-header">Overtime by section</div>
    @if ($report['overtime']['sections'] === [])
        <div style="{{ $note }}">No approved overtime in these two {{ $unit }}s.</div>
    @else
        @php $peakHours = max(0.01, collect($report['overtime']['sections'])->max(fn ($s) => $s['ot_hours']['current'])); @endphp
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 22%;">Section</th>
                    <th style="width: 30%;">Hours this {{ $unit }}</th>
                    <th class="right">Hours</th><th class="right">Last {{ $unit }}</th><th class="right">{{ $vsLast }}</th>
                    @if ($canPay)
                        <th class="right">Cost (RM)</th><th class="right">{{ $vsLast }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($report['overtime']['sections'] as $s)
                    @php
                        $h = $delta($s['ot_hours']['change'], false);
                        $c = $canPay ? $delta($s['ot_cost']['change'], false) : null;
                    @endphp
                    <tr>
                        <td style="font-weight: bold;">{{ $s['name'] }}</td>
                        <td>{!! $bar($s['ot_hours']['current'], $peakHours, $report['charts']['overtime']['color'], 8) !!}</td>
                        <td class="right">{{ $num($s['ot_hours']['current'], 1) }}</td>
                        <td class="right">{{ $num($s['ot_hours']['previous'], 1) }}</td>
                        <td class="right" style="color: {{ $h[1] }}; font-size: 8pt;">{{ $h[0] }}</td>
                        @if ($canPay)
                            <td class="right">{{ $num($s['ot_cost']['current']) }}</td>
                            <td class="right" style="color: {{ $c[1] }}; font-size: 8pt;">{{ $c[0] }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="{{ $note }}">Each claim counts under the employee's current section.</div>
    @endif

    @php
        $otRows = array_values(array_filter($report['outlets'], fn ($o) =>
            $o['ot_hours']['current'] > 0 || $o['ot_hours']['previous'] > 0
            || ($canPay && ($o['ot_cost']['current'] > 0 || $o['ot_cost']['previous'] > 0))));
    @endphp
    @if ($otRows !== [])
        <div class="section-header">Overtime by outlet</div>
        <table class="items">
            <thead>
                <tr>
                    <th>Outlet</th>
                    <th class="right">Hours</th><th class="right">{{ $vsLast }}</th>
                    @if ($canPay)
                        <th class="right">Cost (RM)</th><th class="right">{{ $vsLast }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($otRows as $o)
                    @php
                        $h = $delta($o['ot_hours']['change'], false);
                        $c = $canPay ? $delta($o['ot_cost']['change'], false) : null;
                    @endphp
                    <tr>
                        <td style="font-weight: bold;">{{ $o['name'] }}</td>
                        <td class="right">{{ $num($o['ot_hours']['current'], 1) }}</td>
                        <td class="right" style="color: {{ $h[1] }}; font-size: 8pt;">{{ $h[0] }}</td>
                        @if ($canPay)
                            <td class="right">{{ $num($o['ot_cost']['current']) }}<div style="font-size: 7pt; color: #64748b;">{{ $pct($o['ot_cost_pct']['current']) }}</div></td>
                            <td class="right" style="color: {{ $c[1] }}; font-size: 8pt;">{{ $c[0] }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <div style="{{ $note }}">
        Approved claims only; {{ $num($report['overtime']['pending_hours']['current'], 1) }} h still awaiting approval this {{ $unit }} are not counted.
        @if ($canPay)
            Cost is estimated at each person's hourly rate of pay; hours settled as time off count as hours but cost nothing.
            @if ($report['overtime']['unpriced'] > 0)
                {{ $report['overtime']['unpriced'] }} approved claim(s) could not be costed: no salary on record.
            @endif
        @endif
    </div>

    {{-- ═══ Labour cost ══════════════════════════════════════════════════ --}}
    @if ($labour !== null)
        <div style="page-break-before: always;"></div>
        <div class="section-header">Labour cost</div>
        @php
            $labourPct = collect($report['kpis'])->firstWhere('key', 'labour_cost')['share'] ?? null;
            $labourRows = array_values(array_filter($report['outlets'], fn ($o) => $o['labour_cost']['current'] > 0 || $o['labour_cost']['previous'] > 0));
        @endphp
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 48%; vertical-align: top; padding-right: 12px;">
                    <table class="items">
                        <thead><tr><th>{{ $cw['range'] }}</th><th class="right">This month</th><th class="right">Last month</th><th class="right">Change</th></tr></thead>
                        <tbody>
                            @foreach ($labour['rows'] as $r)
                                @php $lc = $delta($r['change'], false); @endphp
                                <tr>
                                    <td style="{{ $r['key'] === 'employer_cost' ? 'font-weight: bold;' : '' }}">{{ $r['label'] }}</td>
                                    <td class="right" style="{{ $r['key'] === 'employer_cost' ? 'font-weight: bold;' : '' }}">{{ $num($r['current']) }}</td>
                                    <td class="right">{{ $num($r['previous']) }}</td>
                                    <td class="right" style="color: {{ $lc[1] }}; font-size: 8pt;">{{ $lc[0] }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td>Headcount paid</td>
                                <td class="right">{{ $labour['headcount']['current'] }}</td>
                                <td class="right">{{ $labour['headcount']['previous'] }}</td>
                                <td></td>
                            </tr>
                            @if ($labourPct)
                                @php $lp = $delta($labourPct['change'], false, true); @endphp
                                <tr>
                                    <td>Labour cost % of sales</td>
                                    <td class="right">{{ $pct($labourPct['current']) }}</td>
                                    <td class="right">{{ $pct($labourPct['previous']) }}</td>
                                    <td class="right" style="color: {{ $lp[1] }}; font-size: 8pt;">{{ $lp[0] }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </td>
                <td style="vertical-align: top;">
                    @php $peakLabour = max(0.01, max($report['totals']['labour_cost'])); @endphp
                    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                        <thead>
                            <tr>
                                <th style="width: 18%; text-align: left; {{ $th }}">Month</th>
                                <th style="text-align: left; {{ $th }}">Labour cost</th>
                                <th style="width: 22%; text-align: right; {{ $th }}">RM</th>
                                <th style="width: 14%; text-align: right; {{ $th }}">% sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['periods'] as $i => $p)
                                <tr>
                                    <td style="font-size: 8pt; padding: 2px 6px 2px 0;">{{ $p['label'] }}</td>
                                    <td style="padding: 2px 6px 2px 0;">{!! $bar($report['totals']['labour_cost'][$i], $peakLabour, $report['charts']['labour']['color'], 9) !!}</td>
                                    <td style="text-align: right; font-size: 8pt; padding: 2px 6px 2px 0;">{{ $num($report['totals']['labour_cost'][$i]) }}</td>
                                    <td style="text-align: right; font-size: 8pt; padding: 2px 0;">{{ $pct($report['totals']['labour_pct'][$i]) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        @if ($labourRows !== [])
            <table class="items" style="margin-top: 8px;">
                <thead>
                    <tr>
                        <th>Outlet</th>
                        <th class="right">Labour cost</th><th class="right">{{ $vsLast }}</th>
                        <th class="right">Sales</th><th class="right">Labour %</th><th class="right">{{ $vsLast }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($labourRows as $o)
                        @php
                            $lc = $delta($o['labour_cost']['change'], false);
                            $lp = $delta($o['labour_pct']['change'], false, true);
                        @endphp
                        <tr>
                            <td style="font-weight: bold;">{{ $o['name'] }}</td>
                            <td class="right">{{ $num($o['labour_cost']['current']) }}</td>
                            <td class="right" style="color: {{ $lc[1] }}; font-size: 8pt;">{{ $lc[0] }}</td>
                            <td class="right">{{ $num($o['sales']['current']) }}</td>
                            <td class="right">{{ $pct($o['labour_pct']['current']) }}</td>
                            <td class="right" style="color: {{ $lp[1] }}; font-size: 8pt;">{{ $lp[0] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <div style="{{ $note }}">
            Each employee is counted once a month, from their most settled run (paid, then approved, then draft). A company-wide
            run is split by each employee's current outlet.
            @if (! $labour['include_drafts'] && $labour['drafts_left_out'] > 0)
                {{ $labour['drafts_left_out'] }} draft run(s) in these months are not included.
            @elseif ($labour['draft_used'])
                Includes figures from draft runs, which can still change.
            @endif
        </div>
    @endif

    {{-- ═══ By outlet ════════════════════════════════════════════════════ --}}
    <div style="page-break-before: always;"></div>
    <div class="section-header">By outlet</div>
    @if ($report['outlets'] === [])
        <div style="{{ $note }}">No outlet figures for these two {{ $unit }}s.</div>
    @else
        {{-- A figure no outlet has in either {{ $unit }} gets no column. --}}
        @php
            $has = fn (string $key) => collect($report['outlets'])->contains(
                fn ($o) => abs($o[$key]['current'] ?? 0) > 0.005 || abs($o[$key]['previous'] ?? 0) > 0.005);
        @endphp
        <table class="items">
            <thead>
                <tr>
                    <th>Outlet</th>
                    <th class="right">Sales</th><th class="right">{{ $vsLast }}</th>
                    @if ($has('purchases'))<th class="right">Purchases</th><th class="right">Cost %</th>@endif
                    @if ($has('wastage'))<th class="right">Wastage</th>@endif
                    @if ($has('staff_meal'))<th class="right">Staff meals</th>@endif
                    @if ($has('transfers_in'))<th class="right">Transfers in</th>@endif
                    @if ($has('transfers_out'))<th class="right">Transfers out</th>@endif
                    @if ($has('ot_hours'))<th class="right">OT hours</th>@endif
                    @if ($canPay && $has('ot_cost'))
                        <th class="right">OT cost</th>
                    @endif
                    @if ($labour !== null && $has('labour_cost'))
                        <th class="right">Labour</th><th class="right">Labour %</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($report['outlets'] as $o)
                    @php $s = $delta($o['sales']['change'], true); @endphp
                    <tr>
                        <td style="font-weight: bold;">{{ $o['name'] }}</td>
                        <td class="right">{{ $num($o['sales']['current']) }}</td>
                        <td class="right" style="color: {{ $s[1] }}; font-size: 8pt;">{{ $s[0] }}</td>
                        @if ($has('purchases'))
                            <td class="right">{{ $num($o['purchases']['current']) }}</td>
                            <td class="right">{{ $pct($o['cost_pct']['current']) }}</td>
                        @endif
                        @if ($has('wastage'))<td class="right">{{ $num($o['wastage']['current']) }}<div style="font-size: 7pt; color: #64748b;">{{ $pct($o['wastage_pct']['current']) }}</div></td>@endif
                        @if ($has('staff_meal'))<td class="right">{{ $num($o['staff_meal']['current']) }}<div style="font-size: 7pt; color: #64748b;">{{ $pct($o['staff_meal_pct']['current']) }}</div></td>@endif
                        @if ($has('transfers_in'))<td class="right">{{ $num($o['transfers_in']['current']) }}</td>@endif
                        @if ($has('transfers_out'))<td class="right">{{ $num($o['transfers_out']['current']) }}</td>@endif
                        @if ($has('ot_hours'))<td class="right">{{ $num($o['ot_hours']['current'], 1) }}</td>@endif
                        @if ($canPay && $has('ot_cost'))
                            <td class="right">{{ $num($o['ot_cost']['current']) }}<div style="font-size: 7pt; color: #64748b;">{{ $pct($o['ot_cost_pct']['current']) }}</div></td>
                        @endif
                        @if ($labour !== null && $has('labour_cost'))
                            <td class="right">{{ $num($o['labour_cost']['current']) }}</td>
                            <td class="right">{{ $pct($o['labour_pct']['current']) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
