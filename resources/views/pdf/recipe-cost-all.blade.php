@extends('pdf.layout')

@section('title', 'All Recipe Costs')

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
            <div class="doc-title">All Recipe Costs</div>
            <div class="doc-number">{{ count($recipesData) }} Recipes</div>
        </div>
    </div>

    @foreach ($recipesData as $idx => $data)
        @if ($idx > 0)
            <div style="page-break-before: always;"></div>
            {{-- Repeat header on new pages --}}
            <div class="header">
                <div class="header-left">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" class="company-logo" />
                    @endif
                    <div class="company-name">{{ $brandName }}</div>
                </div>
                <div class="header-right">
                    <div class="doc-title">Recipe Cost Card</div>
                    <div class="doc-number">{{ $idx + 1 }} of {{ count($recipesData) }}</div>
                </div>
            </div>
        @endif

        @php $recipe = $data['recipe']; @endphp

        <table class="meta-table">
            <tr>
                <td class="label">Recipe Name</td>
                <td class="value" style="font-weight: bold; font-size: 11px;">{{ $recipe->name }}</td>
            </tr>
            @if ($recipe->code)
                <tr><td class="label">Code</td><td class="value">{{ $recipe->code }}</td></tr>
            @endif
            @if ($recipe->category)
                <tr><td class="label">Category</td><td class="value">{{ $recipe->category }}</td></tr>
            @endif
            <tr>
                <td class="label">Yield</td>
                <td class="value">{{ rtrim(rtrim(number_format($data['yieldQty'], 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}</td>
            </tr>
        </table>

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
                @foreach ($data['lineData'] as $lidx => $ld)
                    <tr>
                        <td>{{ $lidx + 1 }}</td>
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
                    <td style="text-align: right;">{{ number_format($data['totalCost'], 2) }}</td>
                </tr>
                @if ($data['extraCostTotal'] > 0)
                    @foreach ($data['extraCosts'] as $ec)
                        <tr>
                            <td colspan="6" style="text-align: right; font-weight: normal; font-size: 9px; border-top: none; padding: 2px 6px;">
                                {{ $ec['label'] ?? 'Extra Cost' }}
                            </td>
                            <td style="text-align: right; font-weight: normal; font-size: 9px; border-top: none; padding: 2px 6px;">
                                @if (($ec['type'] ?? 'value') === 'percent')
                                    {{ number_format($data['totalCost'] * floatval($ec['amount'] ?? 0) / 100, 2) }}
                                @else
                                    {{ number_format(floatval($ec['amount'] ?? 0), 2) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endif
                <tr>
                    <td colspan="6" style="text-align: right;">Total Cost</td>
                    <td style="text-align: right;">{{ number_format($data['grandCost'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="6" style="text-align: right;">Cost / {{ $recipe->yieldUom?->abbreviation ?? 'serving' }}</td>
                    <td style="text-align: right;">{{ number_format($data['costPerServing'], 4) }}</td>
                </tr>
            </tfoot>
        </table>

        {{-- Pricing --}}
        @if (collect($data['pricingAnalysis'])->contains(fn ($p) => $p['selling_price'] > 0))
            <table class="items" style="margin-top: 6px;">
                <thead>
                    <tr>
                        <th>Price Class</th>
                        <th class="right">Selling Price</th>
                        <th class="right">Food Cost %</th>
                        <th class="right">Gross Profit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['pricingAnalysis'] as $pa)
                        @if ($pa['selling_price'] > 0)
                            <tr>
                                <td>{{ $pa['name'] }}@if ($pa['is_default']) <span style="font-size: 7px; color: #555;">(Default)</span>@endif</td>
                                <td class="right">{{ number_format($pa['selling_price'], 2) }}</td>
                                <td class="right" style="font-weight: bold;">{{ number_format($pa['food_cost_pct'], 1) }}%</td>
                                <td class="right">{{ number_format($pa['gross_profit'], 2) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @elseif ($data['legacyPrice'] > 0)
            <table class="meta-table" style="margin-top: 6px;">
                <tr><td class="label">Selling Price</td><td class="value">{{ number_format($data['legacyPrice'], 2) }}</td></tr>
                <tr><td class="label">Food Cost %</td><td class="value" style="font-weight: bold;">{{ number_format($data['legacyFoodCostPct'], 1) }}%</td></tr>
            </table>
        @endif
    @endforeach
@endsection
