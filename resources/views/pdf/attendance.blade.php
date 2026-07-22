<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Record</title>
    <style>
        /* Scoped reset — `html` (or `*`) must NOT be reset here: dompdf
           implements @page margins via the root element, so `html { margin: 0 }`
           silently zeroes the page margins. */
        body, div, span, h1, h2, h3, p, img, table, thead, tbody, tr, th, td { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 16mm 14mm; }
        /* DejaVu Sans ships with dompdf and renders the ✓ glyph. */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7px; color: #1a1a1a; line-height: 1.3; }

        .header { display: table; width: 100%; margin-bottom: 10px; border-bottom: 1.5px solid #2d3748; padding-bottom: 7px; }
        .header-left { display: table-cell; vertical-align: middle; width: 55%; }
        .header-right { display: table-cell; vertical-align: middle; width: 45%; text-align: right; }
        .logo { max-height: 30px; max-width: 95px; margin-right: 6px; vertical-align: middle; }
        .brand { font-size: 11px; font-weight: bold; vertical-align: middle; }
        .title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; color: #374151; }
        .meta { font-size: 7px; color: #6b7280; margin-top: 2px; }

        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid th, table.grid td { border: 0.6px solid #cbd5e1; overflow: hidden; }

        thead th {
            background: #2d3748; color: #fff; padding: 2.5px 2px;
            font-size: 6px; text-transform: uppercase; letter-spacing: 0.3px;
            font-weight: bold; text-align: center;
        }
        thead th.info { text-align: left; padding-left: 3px; }
        thead th .dow { display: block; font-size: 5px; font-weight: normal; opacity: 0.75; }
        thead th.sun { background: #7f1d1d; }
        thead th.sat { background: #78350f; }

        tbody td { padding: 2.5px 2px; font-size: 6.5px; vertical-align: middle; }
        td.info { text-align: left; padding-left: 3px; white-space: nowrap; }
        td.name { font-weight: bold; }
        td.num { text-align: center; color: #6b7280; }
        td.day { text-align: center; font-weight: bold; font-size: 6px; padding: 1.5px 0; }
        td.sun-empty { background: #fef2f2; }
        td.total { text-align: center; font-weight: bold; background: #f8fafc; }

        .outlet-row td {
            background: #e2e8f0; font-weight: bold; font-size: 7px;
            padding: 2.5px 4px; color: #1e293b; text-align: left;
        }

        .legend { margin-top: 14px; }
        .legend-title { font-size: 6.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 3px; }
        table.legend-table { width: 100%; border-collapse: collapse; }
        table.legend-table td { padding: 1.5px 8px 1.5px 0; font-size: 6.5px; color: #374151; width: 25%; }
        .swatch {
            display: inline-block; min-width: 22px; padding: 1px 3px; margin-right: 3px;
            border: 0.5px solid rgba(0,0,0,0.12); border-radius: 2px;
            font-weight: bold; font-size: 6px; text-align: center;
        }

        .signatures { display: table; width: 100%; margin-top: 26px; }
        .sig { display: table-cell; width: 33%; padding-right: 35px; }
        .sig-line { border-top: 0.8px solid #475569; margin-top: 32px; padding-top: 3px; font-size: 6.5px; color: #475569; }
        .sig-label { font-size: 7px; font-weight: bold; color: #1e293b; }

        .footer { margin-top: 14px; padding-top: 5px; border-top: 1px solid #e2e8f0; font-size: 6.5px; color: #94a3b8; text-align: right; }
    </style>
</head>
<body>

    @php
        // Info columns get the leftover width after the fixed-width day grid.
        $dayW = count($dates) > 28 ? '3.0%' : '3.4%';
    @endphp

    <div class="header">
        <div class="header-left">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" class="logo" />
            @endif
            <span class="brand">{{ $brandName }}</span>
        </div>
        <div class="header-right">
            <div class="title">Attendance Record</div>
            <div class="meta">
                {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
                @if ($outletName) · {{ $outletName }} @endif
                · {{ $employees->count() }} employee(s)
            </div>
        </div>
    </div>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 2%;">#</th>
                <th class="info" style="width: 13%;">Name</th>
                <th class="info" style="width: 7.5%;">Position</th>
                <th class="info" style="width: 4.5%;">Emp ID</th>
                <th class="info" style="width: 4.5%;">Section</th>
                <th class="info" style="width: 5%;">Date Join</th>
                <th style="width: 3.5%;">Svc Pts</th>
                @foreach ($dates as $d)
                    <th style="width: {{ $dayW }};" class="{{ $d->isSunday() ? 'sun' : ($d->isSaturday() ? 'sat' : '') }}">
                        {{ $d->day }}<span class="dow">{{ substr($d->format('D'), 0, 2) }}</span>
                    </th>
                @endforeach
                <th style="width: 3%;">✓</th>
                <th style="width: 3%;">ABS</th>
            </tr>
        </thead>
        <tbody>
            @php $n = 0; @endphp
            @foreach ($employees->groupBy(fn ($e) => $e->outlet?->name ?? 'No Outlet') as $groupName => $group)
                <tr class="outlet-row">
                    <td colspan="{{ 9 + count($dates) }}">{{ $groupName }} ({{ $group->count() }})</td>
                </tr>
                @foreach ($group as $emp)
                    @php
                        $n++;
                        $present = 0;
                        $absent  = 0;
                    @endphp
                    <tr>
                        <td class="num">{{ $n }}</td>
                        <td class="info name">{{ $emp->name }}</td>
                        <td class="info">{{ $emp->designation }}</td>
                        <td class="info">{{ $emp->staff_id }}</td>
                        <td class="info">{{ $emp->section?->name }}</td>
                        <td class="info">{{ $emp->join_date?->format('d/m/y') }}</td>
                        <td class="num">{{ $emp->service_points_entitlement !== null ? number_format((float) $emp->service_points_entitlement, 2) : '' }}</td>
                        @foreach ($dates as $d)
                            @php
                                $codeId = $cellMap[$emp->id . ':' . $d->format('Y-m-d')] ?? null;
                                $code   = $codeId ? ($codesById[$codeId] ?? null) : null;
                                $meta   = $code?->colorMeta();
                                if ($code?->system_key === 'present') $present++;
                                if ($code?->system_key === 'absent')  $absent++;
                            @endphp
                            @if ($code)
                                <td class="day" style="background: {{ $meta['bg'] }}; color: {{ $meta['text'] }};">{{ $code->code }}</td>
                            @else
                                <td class="day {{ $d->isSunday() ? 'sun-empty' : '' }}"></td>
                            @endif
                        @endforeach
                        <td class="total" style="color: #15803d;">{{ $present ?: '' }}</td>
                        <td class="total" style="color: #b91c1c;">{{ $absent ?: '' }}</td>
                    </tr>
                @endforeach
            @endforeach
            @if ($employees->isEmpty())
                <tr><td colspan="{{ 9 + count($dates) }}" style="text-align: center; color: #94a3b8; padding: 10px;">No employees match the selected filters.</td></tr>
            @endif
        </tbody>
    </table>

    <div class="legend">
        <div class="legend-title">Legend</div>
        <table class="legend-table">
            @foreach ($legendCodes->chunk(4) as $chunk)
                <tr>
                    @foreach ($chunk as $code)
                        @php $meta = $code->colorMeta(); @endphp
                        <td>
                            <span class="swatch" style="background: {{ $meta['bg'] }}; color: {{ $meta['text'] }};">{{ $code->code }}</span>
                            {{ $code->label }}
                        </td>
                    @endforeach
                    @for ($i = $chunk->count(); $i < 4; $i++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    </div>

    <div class="signatures">
        <div class="sig">
            <div class="sig-label">Prepared by:</div>
            <div class="sig-line">Name / Signature / Date</div>
        </div>
        <div class="sig">
            <div class="sig-label">Checked by:</div>
            <div class="sig-line">Name / Signature / Date</div>
        </div>
        <div class="sig">
            <div class="sig-label">Approved by:</div>
            <div class="sig-line">Name / Signature / Date</div>
        </div>
    </div>

    <div class="footer">
        Generated by Servora · {{ $brandName }} · {{ now()->format('d M Y, h:i A') }}
    </div>

</body>
</html>
