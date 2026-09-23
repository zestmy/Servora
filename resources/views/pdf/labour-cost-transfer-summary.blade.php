@extends('pdf.layout')

@section('title', 'Labour Cost Transfer Summary')

@section('content')
    @include('pdf.partials.ot-claim-styles')
    @include('pdf.partials.transfer-doc-styles')

    @php
        $signed = fn (float $v) => ($v > 0 ? '+' : ($v < 0 ? '−' : '')) . number_format(abs($v), 2);
        $total  = (float) $transfers->sum(fn ($t) => $t->lines->sum('total_amount'));
        $lines  = $transfers->pluck('lines')->flatten();
    @endphp

    <div class="ot-header">
        <div class="hl">
            @if ($logo = $company?->logoDataUri())
                <img src="{{ $logo }}" class="company-logo" alt="">
            @endif
            <div class="company-name">{{ $company->brand_name ?? $company->name }}</div>
            @if ($company->registration_number)
                <div class="company-detail">Reg No: {{ $company->registration_number }}</div>
            @endif
        </div>
        <div class="hr">
            <div class="doc-title">Labour Cost Transfer</div>
            <div class="doc-number">Summary by Outlet</div>
            <div class="doc-status">Confirmed</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-cell left">
            <div class="info-card">
                <h4>Period</h4>
                <div class="info-body">
                    <div class="kv"><div class="k">Dates</div><div class="v">{{ \Illuminate\Support\Carbon::parse($scope['from'])->format('j M Y') }} – {{ \Illuminate\Support\Carbon::parse($scope['to'])->format('j M Y') }}</div></div>
                    <div class="kv"><div class="k">Outlet</div><div class="v">{{ $scope['outlet'] }}</div></div>
                    <div class="kv"><div class="k">Transfers</div><div class="v">{{ $transfers->count() }} confirmed{{ $draftCount ? ' · ' . $draftCount . ' draft not included' : '' }}</div></div>
                </div>
            </div>
        </div>
        <div class="info-cell right">
            <div class="info-card">
                <h4>Cost Moved</h4>
                <div class="info-body">
                    <div class="kv"><div class="k">Staff / Time</div><div class="v">{{ $lines->pluck('employee_id')->unique()->count() }} staff · {{ collect([
                        $lines->sum('days') > 0 ? number_format((float) $lines->sum('days'), 1) . ' days' : null,
                        $lines->sum('hours') > 0 ? number_format((float) $lines->sum('hours'), 1) . ' hrs' : null,
                    ])->filter()->join(' + ') ?: '—' }}</div></div>
                    <div class="hours-breakdown">
                        <table class="hours-table">
                            <tr><td class="type-label">Salary (days / hours)</td><td class="type-hours">RM {{ number_format((float) $lines->sum('salary_amount'), 2) }}</td></tr>
                            <tr><td class="type-label">Overtime ({{ number_format((float) $lines->sum('ot_hours'), 2) }} hrs)</td><td class="type-hours">RM {{ number_format((float) $lines->sum('ot_amount'), 2) }}</td></tr>
                            <tr class="total"><td class="type-label">Total Transferred</td><td class="type-hours">RM {{ number_format($total, 2) }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($transfers->isEmpty())
        <div class="notes"><h4>No transfers</h4><p>No confirmed labour cost transfers are dated in this period{{ $draftCount ? ' (' . $draftCount . ' still in draft)' : '' }}.</p></div>
    @else
        <div class="section-title">Net by Outlet</div>
        <table class="items compact">
            <thead>
                <tr>
                    <th style="width: 24%;">Outlet</th>
                    <th class="num">Staff Sent</th>
                    <th class="num">Days</th>
                    <th class="num">Hours</th>
                    <th class="num">OT Hrs</th>
                    <th class="num">Lent (RM)</th>
                    <th class="num">Borrowed (RM)</th>
                    <th class="num">Net (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary as $row)
                    <tr>
                        <td style="font-weight: bold;">{{ $outletNames[$row['outlet_id']] ?? '—' }}</td>
                        <td class="num">{{ $row['staff'] ?: '—' }}</td>
                        <td class="num">{{ $row['days'] ? number_format($row['days'], 1) : '—' }}</td>
                        <td class="num">{{ $row['hours'] ? number_format($row['hours'], 2) : '—' }}</td>
                        <td class="num">{{ $row['ot_hours'] ? number_format($row['ot_hours'], 2) : '—' }}</td>
                        <td class="num">{{ $row['sent'] ? number_format($row['sent'], 2) : '—' }}</td>
                        <td class="num">{{ $row['received'] ? number_format($row['received'], 2) : '—' }}</td>
                        <td class="num {{ $row['net'] > 0 ? 'net-pos' : ($row['net'] < 0 ? 'net-neg' : '') }}">{{ $signed($row['net']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @foreach ($sections as $section)
            <div style="page-break-inside: avoid;">
                <div class="section-title">
                    {{ $section['name'] }}
                    <span style="float: right; letter-spacing: 0.4px;" class="{{ ($section['borrowed_total'] - $section['lent_total']) > 0 ? 'net-pos' : 'net-neg' }}">
                        Net {{ $signed($section['borrowed_total'] - $section['lent_total']) }}
                    </span>
                </div>

                @foreach ([
                    'borrowed' => ['Borrowed — cost taken on', 'From'],
                    'lent'     => ['Lent — cost handed off', 'To'],
                ] as $key => [$heading, $otherLabel])
                    @if (count($section[$key]))
                        <p style="font-size: 7.5pt; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 1px; margin: 4px 0 3px;">{{ $heading }}</p>
                        <table class="items compact" style="margin-bottom: 6px;">
                            <thead>
                                <tr>
                                    <th style="width: 18%;">Transfer</th>
                                    <th style="width: 16%;">Employee</th>
                                    <th style="width: 13%;">{{ $otherLabel }}</th>
                                    <th style="width: 15%;">Dates</th>
                                    <th class="num" style="width: 8%;">Basis</th>
                                    <th class="num" style="width: 9%;">Salary</th>
                                    <th class="num" style="width: 6%;">OT Hrs</th>
                                    <th class="num" style="width: 7%;">OT</th>
                                    <th class="num" style="width: 8%;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($section[$key] as $r)
                                    <tr>
                                        <td style="white-space: nowrap;">{{ $r['transfer'] }}<span class="sub" style="white-space: normal;">{{ \Illuminate\Support\Str::limit($r['purpose'], 30) }}</span></td>
                                        <td style="font-weight: bold;">{{ $r['employee'] }}</td>
                                        <td>{{ $r['other'] }}</td>
                                        <td style="white-space: nowrap;">{{ $r['dates'] }}</td>
                                        <td class="num">{{ $r['basis'] }}</td>
                                        <td class="num">{{ number_format($r['salary'], 2) }}</td>
                                        <td class="num">{{ number_format($r['ot_hours'], 2) }}</td>
                                        <td class="num">{{ number_format($r['ot'], 2) }}</td>
                                        <td class="num" style="font-weight: bold;">{{ number_format($r['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="grand">
                                    <td colspan="8" class="total-label" style="text-align: right;">{{ $key === 'lent' ? 'Total lent' : 'Total borrowed' }}</td>
                                    <td class="num">{{ number_format($section[$key . '_total'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                @endforeach
            </div>
        @endforeach
    @endif

    <div class="computer-generated-note">
        Confirmed transfers dated in the period. Net: + takes the cost on (borrowed staff), − hands it off (lent staff); across all outlets it nets to zero.
        Labour reports spread each line over its own dates, so a stint crossing a month end is split there and may differ slightly from this list.
    </div>
@endsection
