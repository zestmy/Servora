<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Employee List</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 15mm 12mm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 8px; color: #1a1a1a; line-height: 1.35; padding: 5mm; }

        .header { display: table; width: 100%; margin-bottom: 8px; border-bottom: 1.5px solid #2d3748; padding-bottom: 6px; }
        .header-left { display: table-cell; vertical-align: middle; width: 60%; }
        .header-right { display: table-cell; vertical-align: middle; width: 40%; text-align: right; }
        .logo { max-height: 32px; max-width: 100px; margin-right: 6px; vertical-align: middle; }
        .brand { font-size: 11px; font-weight: bold; vertical-align: middle; }
        .title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #666; }
        .meta { font-size: 7px; color: #999; margin-top: 2px; }

        .filters { font-size: 7.5px; color: #555; margin-bottom: 6px; padding: 3px 6px; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 2px; }
        .filters strong { color: #2d3748; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #2d3748; color: #fff; padding: 3.5px 5px;
            font-size: 7px; text-transform: uppercase; letter-spacing: 0.5px;
            text-align: left; font-weight: 600;
        }
        thead th.c { text-align: center; }
        tbody td {
            padding: 3px 5px; font-size: 8px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }
        tbody td.c { text-align: center; }
        tbody tr:nth-child(even) { background: #fafbfc; }

        .outlet-row td { background: #edf2f7; font-weight: bold; font-size: 8px; padding: 3.5px 5px; color: #2d3748; }

        .pill { display: inline-block; padding: 0.5px 5px; border-radius: 6px; font-size: 7px; font-weight: bold; white-space: nowrap; }
        .pill-green  { background: #dcfce7; color: #15803d; }
        .pill-gray   { background: #f1f5f9; color: #64748b; }
        .pill-amber  { background: #fef3c7; color: #b45309; }
        .pill-orange { background: #ffedd5; color: #c2410c; }
        .pill-blue   { background: #dbeafe; color: #1d4ed8; }
        .pill-red    { background: #fee2e2; color: #b91c1c; }
        .sub { font-size: 6.5px; color: #94a3b8; margin-top: 1px; white-space: nowrap; }
        .sub-red { color: #dc2626; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; }

        .footer { margin-top: 8px; padding-top: 4px; border-top: 1px solid #e2e8f0; font-size: 7px; color: #999; text-align: right; }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-left">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" class="logo" />
            @endif
            <span class="brand">{{ $brandName }}</span>
        </div>
        <div class="header-right">
            <div class="title">Employee List</div>
            <div class="meta">{{ now()->format('d M Y, h:i A') }} · {{ $employees->count() }} employee(s) · {{ $employees->where('is_active', true)->count() }} active</div>
        </div>
    </div>

    @if (! empty($filters))
        <div class="filters">
            <strong>Filters:</strong> {{ implode(' · ', $filters) }}
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 16px;">#</th>
                <th>Name</th>
                <th style="width: 52px;">Staff ID</th>
                <th style="width: 75px;">Designation</th>
                <th style="width: 50px;">Section</th>
                <th style="width: 95px;">E-mail</th>
                <th style="width: 65px;">Phone</th>
                <th style="width: 52px;">Join Date</th>
                <th style="width: 82px;" class="c">Employment</th>
                <th style="width: 72px;" class="c">Food Handler</th>
                <th style="width: 72px;" class="c">Typhoid Card</th>
                <th style="width: 40px;" class="c">Status</th>
            </tr>
        </thead>
        <tbody>
            @php
                $n = 0;
                $esPills = [
                    'probation'          => 'pill-amber',
                    'confirmed'          => 'pill-green',
                    'extended_probation' => 'pill-orange',
                    'outsourcing'        => 'pill-blue',
                ];
            @endphp
            @foreach ($employees->groupBy(fn ($e) => $e->outlet?->name ?? 'No Outlet') as $outletName => $group)
                <tr class="outlet-row">
                    <td colspan="12">{{ $outletName }} ({{ $group->count() }})</td>
                </tr>
                @foreach ($group as $emp)
                    @php
                        $n++;
                        $probationOverdue = in_array($emp->employment_status, ['probation', 'extended_probation'], true)
                            && $emp->employment_status_date?->isBefore(today());
                        $typhoidExpired = $emp->typhoid_card && $emp->typhoid_expired_on?->isBefore(today());
                    @endphp
                    <tr>
                        <td>{{ $n }}</td>
                        <td style="font-weight: 600;">{{ $emp->name }}</td>
                        <td class="mono">{{ $emp->staff_id ?? '—' }}</td>
                        <td>{{ $emp->designation ?? '—' }}</td>
                        <td>{{ $emp->section?->name ?? '—' }}</td>
                        <td>{{ $emp->email ?? '—' }}</td>
                        <td>{{ $emp->phone ?? '—' }}</td>
                        <td>{{ $emp->join_date?->format('d M Y') ?? '—' }}</td>
                        <td class="c">
                            @if ($emp->employment_status)
                                <span class="pill {{ $probationOverdue ? 'pill-red' : $esPills[$emp->employment_status] }}">{{ $emp->employmentStatusLabel() }}</span>
                                @if ($emp->employmentStatusDetail())
                                    <div class="sub {{ $probationOverdue ? 'sub-red' : '' }}">{{ $emp->employmentStatusDetail() }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="c">
                            <span class="pill {{ $emp->food_handler_certified ? 'pill-green' : 'pill-gray' }}">{{ $emp->food_handler_certified ? 'Certified' : 'No' }}</span>
                            @if ($emp->food_handler_certified && $emp->food_handler_cert_no)
                                <div class="sub mono">{{ $emp->food_handler_cert_no }}</div>
                            @endif
                        </td>
                        <td class="c">
                            <span class="pill {{ $typhoidExpired ? 'pill-red' : ($emp->typhoid_card ? 'pill-green' : 'pill-gray') }}">{{ $typhoidExpired ? 'Expired' : ($emp->typhoid_card ? 'Yes' : 'No') }}</span>
                            @if ($emp->typhoid_card && $emp->typhoid_expired_on)
                                <div class="sub {{ $typhoidExpired ? 'sub-red' : '' }}">{{ $typhoidExpired ? 'expired' : 'until' }} {{ $emp->typhoid_expired_on->format('d M Y') }}</div>
                            @endif
                        </td>
                        <td class="c">
                            <span class="pill {{ $emp->is_active ? 'pill-green' : 'pill-gray' }}">{{ $emp->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                    </tr>
                @endforeach
            @endforeach
            @if ($employees->isEmpty())
                <tr><td colspan="12" style="text-align: center; color: #999; padding: 12px;">No employees match the selected filters.</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Generated by Servora · {{ $brandName }}
    </div>

</body>
</html>
