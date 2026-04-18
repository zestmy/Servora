@extends('pdf.layout')

@section('title', 'Price History Report — ' . $filters['from'] . ' to ' . $filters['to'])

@section('content')
<style>
    .ph-header { display: table; width: 100%; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid #0f172a; }
    .ph-header .hl { display: table-cell; vertical-align: middle; width: 62%; }
    .ph-header .hr { display: table-cell; vertical-align: middle; width: 38%; text-align: right; }
    .ph-header .company-name { font-size: 12pt; font-weight: bold; color: #0f172a; }
    .ph-header .company-detail { font-size: 7.5pt; color: #64748b; margin-top: 1px; }
    .ph-header .company-logo { max-height: 38px; max-width: 140px; margin-bottom: 3px; display: inline-block; }
    .ph-header .doc-title { font-size: 10.5pt; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 1.5px; }
    .ph-header .doc-sub   { font-size: 8pt; color: #64748b; margin-top: 2px; }

    /* Filters + stat bar */
    table.filter-bar { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
    table.filter-bar td { padding: 3px 8px; font-size: 7.5pt; color: #334155; vertical-align: top; border: 1px solid #e2e8f0; background: #f8fafc; }
    table.filter-bar td .k { color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-size: 6.5pt; display: block; font-weight: bold; }
    table.filter-bar td .v { color: #0f172a; font-weight: bold; }

    table.stats { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
    table.stats td { padding: 5px 8px; border: 1px solid #e2e8f0; background: #fff; text-align: center; font-size: 8pt; width: 16.66%; }
    table.stats td .k { color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; font-size: 6.5pt; display: block; font-weight: bold; margin-bottom: 2px; }
    table.stats td .v { color: #0f172a; font-weight: bold; font-size: 11pt; }
    table.stats td.up   .v { color: #b91c1c; }
    table.stats td.down .v { color: #15803d; }

    /* Main table */
    table.rows { width: 100%; border-collapse: collapse; }
    table.rows thead th {
        background: #1f2937; color: #fff;
        padding: 5px 6px;
        font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px;
        text-align: left; font-weight: bold;
    }
    table.rows thead th.right  { text-align: right; }
    table.rows thead th.center { text-align: center; }
    table.rows tbody td {
        padding: 4px 6px; border-bottom: 1px solid #e5e7eb;
        font-size: 8pt; color: #1f2937; vertical-align: top;
    }
    table.rows tbody td.right  { text-align: right; font-variant-numeric: tabular-nums; }
    table.rows tbody td.center { text-align: center; }
    table.rows tbody tr:nth-child(even) td { background: #f9fafb; }

    .pill { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7pt; font-weight: bold; letter-spacing: 0.3px; }
    .pill-up   { color: #b91c1c; background: #fef2f2; }
    .pill-down { color: #15803d; background: #f0fdf4; }
    .pill-flat { color: #64748b; background: #f1f5f9; }

    .muted { color: #94a3b8; }
    .small { font-size: 7pt; color: #94a3b8; margin-top: 2px; }
</style>

<div class="ph-header">
    <div class="hl">
        @if ($company?->logo)
            <img src="{{ public_path('storage/' . $company->logo) }}" class="company-logo" alt="">
        @endif
        <div class="company-name">{{ $company->brand_name ?? $company->name }}</div>
        @if ($company->registration_number)
            <div class="company-detail">Reg No: {{ $company->registration_number }}</div>
        @endif
    </div>
    <div class="hr">
        <div class="doc-title">Price History &amp; Changes</div>
        <div class="doc-sub">{{ $filters['from'] }} — {{ $filters['to'] }}</div>
    </div>
</div>

@php
    $movementLabel = match ($filters['movement'] ?? 'all') {
        'increase'  => 'Increase only',
        'decrease'  => 'Decrease only',
        'unchanged' => 'No change',
        default     => 'All movement',
    };
@endphp
<table class="filter-bar">
    <tr>
        <td><span class="k">Date range</span><span class="v">{{ $filters['from'] }} → {{ $filters['to'] }}</span></td>
        <td><span class="k">Supplier</span><span class="v">{{ $filters['supplier'] ?? 'All suppliers' }}</span></td>
        <td><span class="k">Category</span><span class="v">{{ $filters['category'] ?? 'All categories' }}</span></td>
        <td><span class="k">Movement</span><span class="v">{{ $movementLabel }}</span></td>
        <td><span class="k">Search</span><span class="v">{{ $filters['search'] ?? '—' }}</span></td>
        <td><span class="k">Sort</span><span class="v">{{ ucfirst($filters['sort']) }}</span></td>
        <td><span class="k">Rows</span><span class="v">{{ $rows->count() }}</span></td>
    </tr>
</table>

<table class="stats">
    <tr>
        <td>
            <span class="k">Records</span>
            <span class="v">{{ number_format($stats['totalRecords']) }}</span>
        </td>
        <td>
            <span class="k">Ingredients</span>
            <span class="v">{{ number_format($stats['uniqueIngredients']) }}</span>
        </td>
        <td class="up">
            <span class="k">Increases</span>
            <span class="v">{{ $stats['increases'] }}</span>
        </td>
        <td class="down">
            <span class="k">Decreases</span>
            <span class="v">{{ $stats['decreases'] }}</span>
        </td>
        <td>
            <span class="k">Avg change</span>
            <span class="v {{ $stats['avgChangePct'] > 0 ? 'up' : ($stats['avgChangePct'] < 0 ? 'down' : '') }}">
                {{ $stats['avgChangePct'] > 0 ? '+' : '' }}{{ $stats['avgChangePct'] }}%
            </span>
        </td>
        <td>
            <span class="k">Biggest mover</span>
            <span class="v">
                @if ($stats['biggestIncreaseName'])
                    {{ \Illuminate\Support\Str::limit($stats['biggestIncreaseName'], 18) }}
                    <span class="pill pill-up">+{{ $stats['biggestIncreasePct'] }}%</span>
                @elseif ($stats['biggestDecreaseName'])
                    {{ \Illuminate\Support\Str::limit($stats['biggestDecreaseName'], 18) }}
                    <span class="pill pill-down">{{ $stats['biggestDecreasePct'] }}%</span>
                @else
                    —
                @endif
            </span>
        </td>
    </tr>
</table>

<table class="rows">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 28%;">Ingredient</th>
            <th style="width: 14%;">Category</th>
            <th style="width: 7%;">UOM</th>
            <th class="center" style="width: 8%;">Records</th>
            <th class="right" style="width: 10%;">First Price</th>
            <th class="right" style="width: 10%;">Latest Price</th>
            <th class="right" style="width: 8%;">Change</th>
            <th class="center" style="width: 10%;">Latest Date</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $i => $row)
            @php
                $first = (float) ($row->first_cost ?? 0);
                $last  = (float) ($row->last_cost  ?? 0);
                $pct   = $first > 0 ? round((($last - $first) / $first) * 100, 1) : null;
                $pill  = $pct === null ? 'pill-flat' : ($pct > 0 ? 'pill-up' : ($pct < 0 ? 'pill-down' : 'pill-flat'));
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    <strong>{{ $row->ingredient_name }}</strong>
                    @if ($row->ingredient_code)
                        <div class="small">{{ $row->ingredient_code }}</div>
                    @endif
                </td>
                <td>{{ $row->category_name ?? '—' }}</td>
                <td>{{ $row->uom ?? '—' }}</td>
                <td class="center">{{ (int) $row->record_count }}</td>
                <td class="right">{{ $first > 0 ? number_format($first, 2) : '—' }}</td>
                <td class="right"><strong>{{ $last > 0 ? number_format($last, 2) : '—' }}</strong></td>
                <td class="right">
                    @if ($pct !== null && $pct !== 0.0)
                        <span class="pill {{ $pill }}">{{ $pct > 0 ? '+' : '' }}{{ $pct }}%</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td class="center">{{ \Illuminate\Support\Carbon::parse($row->latest_date)->format('d M Y') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" style="padding: 20px; text-align: center; color: #94a3b8; font-style: italic;">
                    No price history records for this filter.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
@endsection
