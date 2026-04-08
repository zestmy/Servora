@extends('pdf.layout')

@section('title', 'Recipe Cost Card — ' . $recipe->name)

@section('content')
    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" class="company-logo" />
            @endif
            <div class="company-name">{{ $brandName }}</div>
        </div>
        <div class="header-right">
            <div class="doc-title">Recipe Cost Card</div>
            <div class="doc-number">{{ $recipe->code ?: '#' . $recipe->id }}</div>
        </div>
    </div>

    {{-- Recipe info --}}
    <table class="meta-table">
        <tr>
            <td class="label">Recipe Name</td>
            <td class="value" style="font-weight: bold; font-size: 11px;">{{ $recipe->name }}</td>
        </tr>
        @if ($recipe->category)
            <tr><td class="label">Category</td><td class="value">{{ $recipe->category }}</td></tr>
        @endif
        @if ($recipe->department)
            <tr><td class="label">Department</td><td class="value">{{ $recipe->department->name }}</td></tr>
        @endif
        <tr>
            <td class="label">Yield</td>
            <td class="value">{{ rtrim(rtrim(number_format($yieldQty, 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}</td>
        </tr>
    </table>

    {{-- Ingredients table --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 25px;">#</th>
                <th>Ingredient</th>
                <th class="right" style="width: 60px;">Qty</th>
                <th style="width: 40px;">UOM</th>
                <th class="right" style="width: 50px;">Waste</th>
                <th class="right" style="width: 70px;">Unit Cost</th>
                <th class="right" style="width: 70px;">Line Cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineData as $idx => $ld)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $ld['ingredient'] }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format($ld['quantity'], 4), '0'), '.') }}</td>
                    <td>{{ $ld['uom'] }}</td>
                    <td class="right">{{ $ld['waste_percentage'] > 0 ? number_format($ld['waste_percentage'], 1) . '%' : '—' }}</td>
                    <td class="right">{{ number_format($ld['unit_cost'], 4) }}</td>
                    <td class="right">{{ number_format($ld['line_cost'], 4) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="text-align: right;">Ingredient Cost</td>
                <td style="text-align: right;">{{ number_format($totalCost, 2) }}</td>
            </tr>
            @if (count($extraCosts))
                @foreach ($extraCosts as $ec)
                    <tr>
                        <td colspan="6" style="text-align: right; font-weight: normal; font-size: 9px; border-top: none; padding: 2px 6px;">
                            {{ $ec['label'] ?? 'Extra Cost' }}
                            @if (($ec['type'] ?? 'value') === 'percent')
                                ({{ $ec['amount'] }}%)
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: normal; font-size: 9px; border-top: none; padding: 2px 6px;">
                            @if (($ec['type'] ?? 'value') === 'percent')
                                {{ number_format($totalCost * floatval($ec['amount'] ?? 0) / 100, 2) }}
                            @else
                                {{ number_format(floatval($ec['amount'] ?? 0), 2) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="6" style="text-align: right;">Total Cost</td>
                    <td style="text-align: right;">{{ number_format($grandCost, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="6" style="text-align: right;">Cost per {{ $recipe->yieldUom?->abbreviation ?? 'serving' }}</td>
                <td style="text-align: right;">{{ number_format($costPerServing, 4) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Pricing Analysis --}}
    @if (count($pricingAnalysis) && collect($pricingAnalysis)->contains(fn ($p) => $p['selling_price'] > 0))
        <div style="margin-top: 12px;">
            <table class="items">
                <thead>
                    <tr>
                        <th>Price Class</th>
                        <th class="right">Selling Price</th>
                        <th class="right">Food Cost %</th>
                        <th class="right">Gross Profit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pricingAnalysis as $pa)
                        @if ($pa['selling_price'] > 0)
                            <tr>
                                <td>
                                    {{ $pa['name'] }}
                                    @if ($pa['is_default'])
                                        <span style="font-size: 7px; color: #555;">(Default)</span>
                                    @endif
                                </td>
                                <td class="right">{{ number_format($pa['selling_price'], 2) }}</td>
                                <td class="right" style="font-weight: bold; color: {{ $pa['food_cost_pct'] <= 35 ? '#16a34a' : ($pa['food_cost_pct'] <= 45 ? '#ea580c' : '#dc2626') }};">
                                    {{ number_format($pa['food_cost_pct'], 1) }}%
                                </td>
                                <td class="right">{{ number_format($pa['gross_profit'], 2) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($legacyPrice > 0)
        <div style="margin-top: 12px;">
            <table class="items">
                <thead>
                    <tr>
                        <th>Selling Price</th>
                        <th class="right">Food Cost %</th>
                        <th class="right">Gross Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ number_format($legacyPrice, 2) }}</td>
                        <td class="right" style="font-weight: bold;">{{ number_format($legacyFoodCostPct, 1) }}%</td>
                        <td class="right">{{ number_format($legacyPrice - $grandCost, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
@endsection
