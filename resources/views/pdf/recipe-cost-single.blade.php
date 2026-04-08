<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recipe Cost Card — {{ $recipe->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 9px; color: #1a1a1a; line-height: 1.3; }
        .page { padding: 18px 22px; }

        /* Header */
        .card-header { display: table; width: 100%; margin-bottom: 12px; }
        .card-header-left { display: table-cell; vertical-align: top; width: 55%; }
        .card-header-right { display: table-cell; vertical-align: top; width: 45%; text-align: right; }
        .company-logo { max-height: 28px; max-width: 100px; margin-bottom: 2px; }
        .brand-name { font-size: 11px; font-weight: bold; color: #1a1a1a; }
        .recipe-title { font-size: 14px; font-weight: bold; color: #111; margin-top: 8px; letter-spacing: 0.3px; }
        .recipe-code { font-size: 10px; color: #666; font-family: monospace; margin-top: 1px; }
        .doc-label { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; color: #888; }
        .doc-date { font-size: 8px; color: #888; margin-top: 2px; }

        /* Info strip */
        .info-strip { display: table; width: 100%; margin-bottom: 10px; border: 1px solid #e0e0e0; border-radius: 4px; overflow: hidden; }
        .info-cell { display: table-cell; padding: 5px 8px; border-right: 1px solid #e0e0e0; vertical-align: top; }
        .info-cell:last-child { border-right: none; }
        .info-cell .lbl { font-size: 6.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 1px; }
        .info-cell .val { font-size: 9px; font-weight: 600; color: #222; }

        /* Ingredients table */
        table.ing { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.ing thead th { background: #2d3748; color: #fff; padding: 3px 5px; font-size: 7px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table.ing thead th.r { text-align: right; }
        table.ing tbody td { padding: 2.5px 5px; border-bottom: 1px solid #f0f0f0; font-size: 8px; }
        table.ing tbody td.r { text-align: right; font-variant-numeric: tabular-nums; }
        table.ing tbody tr:nth-child(even) { background: #fafafa; }
        table.ing tfoot td { padding: 3px 5px; font-weight: bold; font-size: 9px; border-top: 1.5px solid #2d3748; }
        table.ing tfoot td.r { text-align: right; }
        table.ing tfoot .sub td { font-weight: normal; font-size: 8px; color: #555; border-top: none; padding: 1.5px 5px; }

        /* Profitability panel */
        .profit-panel { margin-top: 10px; }
        .profit-panel h4 { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #555; margin-bottom: 6px; border-bottom: 1px solid #ddd; padding-bottom: 3px; }
        table.profit { width: 100%; border-collapse: collapse; }
        table.profit thead th { background: #f8f9fa; padding: 3px 6px; font-size: 7px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; border-bottom: 1.5px solid #dee2e6; color: #495057; }
        table.profit thead th.r { text-align: right; }
        table.profit tbody td { padding: 4px 6px; font-size: 8.5px; border-bottom: 1px solid #eee; }
        table.profit tbody td.r { text-align: right; font-variant-numeric: tabular-nums; }
        table.profit tbody td.bold { font-weight: bold; }

        /* Cost summary boxes */
        .cost-boxes { display: table; width: 100%; margin-top: 10px; }
        .cost-box { display: table-cell; width: 25%; padding: 6px 8px; text-align: center; border: 1px solid #e0e0e0; vertical-align: top; }
        .cost-box:first-child { border-radius: 4px 0 0 4px; }
        .cost-box:last-child { border-radius: 0 4px 4px 0; }
        .cost-box .cb-label { font-size: 6.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #888; }
        .cost-box .cb-value { font-size: 12px; font-weight: bold; color: #111; margin-top: 2px; }
        .cost-box .cb-sub { font-size: 7px; color: #888; margin-top: 1px; }
        .cost-box.highlight { background: #f0fdf4; border-color: #bbf7d0; }
        .cost-box.warn { background: #fefce8; border-color: #fde68a; }
        .cost-box.danger { background: #fef2f2; border-color: #fecaca; }

        /* Color helpers */
        .c-green { color: #16a34a; }
        .c-yellow { color: #ca8a04; }
        .c-orange { color: #ea580c; }
        .c-red { color: #dc2626; }

        /* Footer */
        .pdf-footer { margin-top: 14px; padding-top: 5px; border-top: 1px solid #ddd; font-size: 7px; color: #aaa; text-align: center; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="card-header">
        <div class="card-header-left">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" class="company-logo" /><br>
            @endif
            <span class="brand-name">{{ $brandName }}</span>
            <div class="recipe-title">{{ $recipe->name }}</div>
            @if ($recipe->code)
                <div class="recipe-code">{{ $recipe->code }}</div>
            @endif
        </div>
        <div class="card-header-right">
            <div class="doc-label">Recipe Cost Card</div>
            <div class="doc-date">{{ now()->format('d M Y') }}</div>
        </div>
    </div>

    {{-- Info strip --}}
    <div class="info-strip">
        @if ($recipe->category)
            <div class="info-cell">
                <div class="lbl">Category</div>
                <div class="val">{{ $recipe->category }}</div>
            </div>
        @endif
        @if ($recipe->department)
            <div class="info-cell">
                <div class="lbl">Department</div>
                <div class="val">{{ $recipe->department->name }}</div>
            </div>
        @endif
        <div class="info-cell">
            <div class="lbl">Yield</div>
            <div class="val">{{ rtrim(rtrim(number_format($yieldQty, 4), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}</div>
        </div>
        <div class="info-cell">
            <div class="lbl">Cost / {{ $recipe->yieldUom?->abbreviation ?? 'Serving' }}</div>
            <div class="val">{{ number_format($costPerServing, 4) }}</div>
        </div>
    </div>

    {{-- Ingredients table --}}
    <table class="ing">
        <thead>
            <tr>
                <th style="width: 20px;">#</th>
                <th>Ingredient</th>
                <th class="r" style="width: 52px;">Qty</th>
                <th style="width: 35px;">UOM</th>
                <th class="r" style="width: 42px;">Waste</th>
                <th class="r" style="width: 62px;">Unit Cost</th>
                <th class="r" style="width: 62px;">Line Cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lineData as $idx => $ld)
                <tr>
                    <td style="color: #999;">{{ $idx + 1 }}</td>
                    <td style="font-weight: 500;">{{ $ld['ingredient'] }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format($ld['quantity'], 4), '0'), '.') }}</td>
                    <td>{{ $ld['uom'] }}</td>
                    <td class="r" style="color: {{ $ld['waste_percentage'] > 0 ? '#ea580c' : '#ccc' }};">{{ $ld['waste_percentage'] > 0 ? number_format($ld['waste_percentage'], 1) . '%' : '—' }}</td>
                    <td class="r">{{ number_format($ld['unit_cost'], 4) }}</td>
                    <td class="r" style="font-weight: 600;">{{ number_format($ld['line_cost'], 4) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="r">Ingredient Cost</td>
                <td class="r">{{ number_format($totalCost, 2) }}</td>
            </tr>
            @if (count($extraCosts))
                @foreach ($extraCosts as $ec)
                    <tr class="sub">
                        <td colspan="6" class="r">
                            {{ $ec['label'] ?? 'Extra Cost' }}
                            @if (($ec['type'] ?? 'value') === 'percent') ({{ $ec['amount'] }}%) @endif
                        </td>
                        <td class="r">
                            {{ number_format(($ec['type'] ?? 'value') === 'percent' ? $totalCost * floatval($ec['amount'] ?? 0) / 100 : floatval($ec['amount'] ?? 0), 2) }}
                        </td>
                    </tr>
                @endforeach
            @endif
            @if ($extraCostTotal > 0)
                <tr>
                    <td colspan="6" class="r">Total Cost</td>
                    <td class="r" style="font-size: 10px;">{{ number_format($grandCost, 2) }}</td>
                </tr>
            @endif
        </tfoot>
    </table>

    {{-- Cost summary boxes --}}
    @php
        $defaultPa = collect($pricingAnalysis)->firstWhere('is_default', true);
        if (! $defaultPa || $defaultPa['selling_price'] <= 0) {
            $defaultPa = collect($pricingAnalysis)->first(fn ($p) => $p['selling_price'] > 0);
        }
        $mainSp = $defaultPa ? $defaultPa['selling_price'] : $legacyPrice;
        $mainFc = $mainSp > 0 ? ($grandCost / $mainSp) * 100 : null;
        $mainGp = $mainSp > 0 ? $mainSp - $grandCost : null;
        $mainMargin = $mainSp > 0 ? (($mainSp - $grandCost) / $mainSp) * 100 : null;
        $mainMarkup = $grandCost > 0 ? (($mainSp - $grandCost) / $grandCost) * 100 : null;
        $costRatio = $mainSp > 0 ? $grandCost / $mainSp : null;

        $fcBoxClass = match(true) {
            $mainFc === null => '',
            $mainFc <= 30    => 'highlight',
            $mainFc <= 40    => 'warn',
            default          => 'danger',
        };
        $fcTextClass = match(true) {
            $mainFc === null => '',
            $mainFc <= 25    => 'c-green',
            $mainFc <= 35    => 'c-yellow',
            $mainFc <= 45    => 'c-orange',
            default          => 'c-red',
        };
    @endphp
    @if ($mainSp > 0)
        <div class="cost-boxes">
            <div class="cost-box">
                <div class="cb-label">Total Cost</div>
                <div class="cb-value">{{ number_format($grandCost, 2) }}</div>
                <div class="cb-sub">per recipe batch</div>
            </div>
            <div class="cost-box {{ $fcBoxClass }}">
                <div class="cb-label">Food Cost %</div>
                <div class="cb-value {{ $fcTextClass }}">{{ number_format($mainFc, 1) }}%</div>
                <div class="cb-sub">cost-to-price ratio</div>
            </div>
            <div class="cost-box highlight">
                <div class="cb-label">Gross Profit</div>
                <div class="cb-value c-green">{{ number_format($mainGp, 2) }}</div>
                <div class="cb-sub">margin {{ number_format($mainMargin, 1) }}%</div>
            </div>
            <div class="cost-box">
                <div class="cb-label">Markup</div>
                <div class="cb-value">{{ number_format($mainMarkup, 1) }}%</div>
                <div class="cb-sub">ratio 1 : {{ $costRatio !== null ? number_format(1 / $costRatio, 2) : '—' }}</div>
            </div>
        </div>
    @endif

    {{-- Profitability per price class --}}
    @php $activePrices = collect($pricingAnalysis)->filter(fn ($p) => $p['selling_price'] > 0); @endphp
    @if ($activePrices->count() > 0)
        <div class="profit-panel">
            <h4>Profitability by Price Class</h4>
            <table class="profit">
                <thead>
                    <tr>
                        <th>Price Class</th>
                        <th class="r">Selling Price</th>
                        <th class="r">Food Cost %</th>
                        <th class="r">Gross Profit</th>
                        <th class="r">Profit Margin</th>
                        <th class="r">Markup %</th>
                        <th class="r">Cost Ratio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activePrices as $pa)
                        @php
                            $sp = $pa['selling_price'];
                            $gp = $pa['gross_profit'];
                            $margin = $sp > 0 ? ($gp / $sp) * 100 : 0;
                            $markup = $grandCost > 0 ? ($gp / $grandCost) * 100 : 0;
                            $ratio = $sp > 0 ? $grandCost / $sp : 0;
                            $rowFcClass = match(true) {
                                $pa['food_cost_pct'] <= 25 => 'c-green',
                                $pa['food_cost_pct'] <= 35 => 'c-yellow',
                                $pa['food_cost_pct'] <= 45 => 'c-orange',
                                default                    => 'c-red',
                            };
                        @endphp
                        <tr>
                            <td class="bold">
                                {{ $pa['name'] }}
                                @if ($pa['is_default']) <span style="font-size: 6.5px; color: #888;">(Default)</span> @endif
                            </td>
                            <td class="r bold">{{ number_format($sp, 2) }}</td>
                            <td class="r bold {{ $rowFcClass }}">{{ number_format($pa['food_cost_pct'], 1) }}%</td>
                            <td class="r" style="color: {{ $gp >= 0 ? '#16a34a' : '#dc2626' }};">{{ number_format($gp, 2) }}</td>
                            <td class="r">{{ number_format($margin, 1) }}%</td>
                            <td class="r">{{ number_format($markup, 1) }}%</td>
                            <td class="r">1 : {{ number_format(1 / max($ratio, 0.0001), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif ($legacyPrice > 0)
        @php
            $gp = $legacyPrice - $grandCost;
            $margin = ($gp / $legacyPrice) * 100;
            $markup = $grandCost > 0 ? ($gp / $grandCost) * 100 : 0;
            $ratio = $grandCost / $legacyPrice;
        @endphp
        <div class="profit-panel">
            <h4>Profitability Analysis</h4>
            <table class="profit">
                <thead>
                    <tr>
                        <th>Selling Price</th>
                        <th class="r">Food Cost %</th>
                        <th class="r">Gross Profit</th>
                        <th class="r">Profit Margin</th>
                        <th class="r">Markup %</th>
                        <th class="r">Cost Ratio</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="bold">{{ number_format($legacyPrice, 2) }}</td>
                        <td class="r bold {{ $legacyFoodCostPct <= 25 ? 'c-green' : ($legacyFoodCostPct <= 35 ? 'c-yellow' : ($legacyFoodCostPct <= 45 ? 'c-orange' : 'c-red')) }}">{{ number_format($legacyFoodCostPct, 1) }}%</td>
                        <td class="r" style="color: {{ $gp >= 0 ? '#16a34a' : '#dc2626' }};">{{ number_format($gp, 2) }}</td>
                        <td class="r">{{ number_format($margin, 1) }}%</td>
                        <td class="r">{{ number_format($markup, 1) }}%</td>
                        <td class="r">1 : {{ number_format(1 / max($ratio, 0.0001), 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <div class="pdf-footer">
        Generated on {{ now()->format('d M Y, h:i A') }}{{ isset($exportedBy) ? ' by ' . $exportedBy : '' }} | {{ $brandName }} | Powered by Servora
    </div>

</div>
</body>
</html>
