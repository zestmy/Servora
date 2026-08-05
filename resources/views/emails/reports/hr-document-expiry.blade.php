@extends('emails.reports.layout')

{{--
    Staff document and training expiry reminder.

    Built from the same DocumentExpiry summary as the Employees screen card, so
    this list and that one can never disagree. Read on a phone by someone about
    to chase a clinic appointment, so it leads with what is already lapsed and
    keeps every row to one line: who, what, when.

    All styling is inline — the layout's classes exist, but mail clients treat
    a <style> block as optional and this table has to survive Outlook.
--}}
@php
    use App\Services\Hr\DocumentExpiry as DE;

    $counts   = $reportData['counts'] ?? [];
    $rows     = collect($reportData['rows'] ?? []);
    $docs     = $reportData['documents'] ?? [];
    $expired  = $counts[DE::EXPIRED] ?? 0;
    $expiring = $counts[DE::EXPIRING] ?? 0;
    $undated  = $counts[DE::UNDATED] ?? 0;
    $missing  = $counts[DE::MISSING] ?? 0;

    $tone = [
        DE::EXPIRED  => ['#b91c1c', '#fee2e2'],
        DE::EXPIRING => ['#b45309', '#fef3c7'],
        DE::UNDATED  => ['#4b5563', '#f3f4f6'],
        DE::MISSING  => ['#4b5563', '#f3f4f6'],
    ];
@endphp

@section('content')
<div class="card">
    <div class="card-header">
        <h1>Staff Documents &amp; Training</h1>
        <p>{{ $outletName }} &bull; {{ $periodLabel }}</p>
    </div>

    <div class="card-body">
        {{-- Headline counts --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
            <tr>
                <td width="50%" style="padding: 0 6px 0 0;">
                    <div style="background: {{ $expired > 0 ? '#fee2e2' : '#f3f4f6' }}; border-radius: 8px; padding: 16px; text-align: center;">
                        <div style="font-size: 28px; font-weight: 700; color: {{ $expired > 0 ? '#b91c1c' : '#9ca3af' }};">{{ $expired }}</div>
                        <div style="font-size: 12px; color: #4b5563; text-transform: uppercase; letter-spacing: .05em;">Expired</div>
                    </div>
                </td>
                <td width="50%" style="padding: 0 0 0 6px;">
                    <div style="background: {{ $expiring > 0 ? '#fef3c7' : '#f3f4f6' }}; border-radius: 8px; padding: 16px; text-align: center;">
                        <div style="font-size: 28px; font-weight: 700; color: {{ $expiring > 0 ? '#b45309' : '#9ca3af' }};">{{ $expiring }}</div>
                        <div style="font-size: 12px; color: #4b5563; text-transform: uppercase; letter-spacing: .05em;">Expiring soon</div>
                    </div>
                </td>
            </tr>
        </table>

        <p style="font-size: 13px; color: #4b5563; margin: 0 0 20px;">
            {{ $reportData['employees'] ?? 0 }} active staff checked.
            &ldquo;Expiring soon&rdquo; means within {{ $reportData['warning_days'] ?? 60 }} days of
            {{ \Carbon\Carbon::parse($reportData['as_of'] ?? now())->format('j M Y') }}.
            @if ($undated > 0 || $missing > 0)
                Also {{ $missing }} not recorded and {{ $undated }} with no expiry date on file.
            @endif
        </p>

        @if ($rows->isEmpty())
            <div style="background: #dcfce7; border-radius: 8px; padding: 20px; text-align: center; color: #15803d; font-size: 14px;">
                Everything is in date. Nothing needs chasing this period.
            </div>
        @else
            {{-- Who needs chasing, most urgent first --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="border-collapse: collapse; font-size: 13px;">
                <tr>
                    <th align="left" style="padding: 8px 6px; border-bottom: 2px solid #e5e7eb; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;">Employee</th>
                    <th align="left" style="padding: 8px 6px; border-bottom: 2px solid #e5e7eb; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;">Document</th>
                    <th align="right" style="padding: 8px 6px; border-bottom: 2px solid #e5e7eb; color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .05em;">Status</th>
                </tr>
                @foreach ($rows as $row)
                    @php [$fg, $bg] = $tone[$row['state']] ?? ['#4b5563', '#f3f4f6']; @endphp
                    <tr>
                        <td style="padding: 10px 6px; border-bottom: 1px solid #f3f4f6; color: #1f2937; font-weight: 600;">
                            {{ $row['name'] }}
                            @if ($row['outlet'])
                                <span style="display: block; font-weight: 400; font-size: 11px; color: #6b7280;">{{ $row['outlet'] }}</span>
                            @endif
                        </td>
                        <td style="padding: 10px 6px; border-bottom: 1px solid #f3f4f6; color: #4b5563;">
                            {{ $row['document'] }}
                            @if ($row['expires_on'])
                                <span style="display: block; font-size: 11px; color: #6b7280;">{{ \Carbon\Carbon::parse($row['expires_on'])->format('j M Y') }}</span>
                            @endif
                        </td>
                        <td align="right" style="padding: 10px 6px; border-bottom: 1px solid #f3f4f6;">
                            <span style="display: inline-block; padding: 3px 8px; border-radius: 999px; background: {{ $bg }}; color: {{ $fg }}; font-size: 11px; font-weight: 600; white-space: nowrap;">
                                @if ($row['state'] === DE::EXPIRED)
                                    expired {{ abs($row['days']) }}d ago
                                @elseif ($row['state'] === DE::EXPIRING)
                                    {{ $row['days'] === 0 ? 'expires today' : 'in ' . $row['days'] . ' days' }}
                                @elseif ($row['state'] === DE::UNDATED)
                                    no expiry date
                                @else
                                    not recorded
                                @endif
                            </span>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif

        {{-- Per-document totals, for the manager who wants the shape of it --}}
        @if (! empty($docs))
            <div class="section" style="margin-top: 28px;">
                <div class="section-title" style="font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 10px;">By document</div>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 12px;">
                    @foreach ($docs as $doc)
                        <tr>
                            <td style="padding: 6px 6px; border-bottom: 1px solid #f3f4f6; color: #374151;">
                                {{ $doc['label'] }}
                                @unless ($doc['required'] ?? true)
                                    <span style="color: #9ca3af; font-size: 11px;">(optional)</span>
                                @endunless
                            </td>
                            <td align="right" style="padding: 6px 6px; border-bottom: 1px solid #f3f4f6; color: #6b7280; white-space: nowrap;">
                                <span style="color: #b91c1c;">{{ $doc[DE::EXPIRED] }}</span> expired &middot;
                                <span style="color: #b45309;">{{ $doc[DE::EXPIRING] }}</span> soon &middot;
                                <span style="color: #15803d;">{{ $doc[DE::VALID] }}</span> valid
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
