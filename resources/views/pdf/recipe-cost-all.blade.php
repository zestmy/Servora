<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $pageTitle ?? 'All Recipe Costs' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 12mm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 8px; color: #1a1a1a; line-height: 1.25; margin: 12mm; }
        .page { padding: 0; }

        /* Page header */
        .page-header { display: table; width: 100%; margin-bottom: 10px; border-bottom: 1.5px solid #2d3748; padding-bottom: 6px; }
        .page-header-left { display: table-cell; vertical-align: middle; width: 60%; }
        .page-header-right { display: table-cell; vertical-align: middle; width: 40%; text-align: right; }
        .company-logo { max-height: 34px; max-width: 120px; margin-right: 6px; vertical-align: middle; }
        .brand-name { font-size: 10px; font-weight: bold; vertical-align: middle; }
        .page-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px; color: #666; }
        .page-count { font-size: 7px; color: #999; }

        /* Recipe card */
        .recipe-card { border: 1px solid #ddd; border-radius: 3px; margin-bottom: 8px; page-break-inside: avoid; overflow: hidden; }
        .card-title-bar { background: #2d3748; color: #fff; padding: 3px 8px; display: table; width: 100%; }
        .card-title-bar .name { font-size: 9px; font-weight: bold; display: table-cell; vertical-align: middle; }
        .card-title-bar .meta { display: table-cell; text-align: right; vertical-align: middle; font-size: 7px; color: #cbd5e0; }
        .card-body { padding: 5px 8px; }

        /* Compact info row */
        .info-row { font-size: 7.5px; color: #555; margin-bottom: 4px; }
        .info-row span { margin-right: 12px; }
        .info-row .lbl { color: #888; font-size: 6.5px; text-transform: uppercase; letter-spacing: 0.3px; }

        /* Ingredients mini table */
        table.ing-mini { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.ing-mini thead th { background: #f7fafc; padding: 2px 4px; font-size: 6.5px; text-transform: uppercase; letter-spacing: 0.3px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #718096; }
        table.ing-mini thead th.r { text-align: right; }
        table.ing-mini tbody td { padding: 1.5px 4px; font-size: 7.5px; border-bottom: 1px solid #f0f0f0; }
        table.ing-mini tbody td.r { text-align: right; font-variant-numeric: tabular-nums; }
        table.ing-mini tbody tr:nth-child(even) { background: #fcfcfc; }
        table.ing-mini tfoot td { padding: 2px 4px; font-weight: bold; font-size: 8px; border-top: 1px solid #2d3748; }
        table.ing-mini tfoot td.r { text-align: right; }
        table.ing-mini tfoot .sub td { font-weight: normal; font-size: 7px; color: #666; border-top: none; padding: 1px 4px; }

        /* Profit strip */
        .profit-strip { display: table; width: 100%; border-top: 1px solid #e2e8f0; margin-top: 3px; padding-top: 3px; }
        .profit-strip .ps-cell { display: table-cell; text-align: center; vertical-align: top; padding: 0 4px; border-right: 1px solid #eee; }
        .profit-strip .ps-cell:last-child { border-right: none; }
        .profit-strip .ps-label { font-size: 6px; text-transform: uppercase; letter-spacing: 0.3px; color: #888; }
        .profit-strip .ps-value { font-size: 8.5px; font-weight: bold; margin-top: 1px; }
        .profit-strip .ps-sub { font-size: 6px; color: #aaa; }

        /* Multi-class mini table */
        table.class-mini { width: 100%; border-collapse: collapse; margin-top: 3px; }
        table.class-mini th { background: #f7fafc; padding: 1.5px 4px; font-size: 6.5px; text-transform: uppercase; letter-spacing: 0.3px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #718096; }
        table.class-mini th.r { text-align: right; }
        table.class-mini td { padding: 2px 4px; font-size: 7.5px; border-bottom: 1px solid #f0f0f0; }
        table.class-mini td.r { text-align: right; font-variant-numeric: tabular-nums; }
        table.class-mini td.bold { font-weight: bold; }

        .c-green { color: #16a34a; }
        .c-yellow { color: #ca8a04; }
        .c-orange { color: #ea580c; }
        .c-red { color: #dc2626; }

        .pdf-footer { margin-top: 10px; padding-top: 4px; border-top: 1px solid #ddd; font-size: 6.5px; color: #aaa; text-align: center; }

        /* Category section heading */
        .cat-heading {
            font-size: 11px;
            font-weight: bold;
            color: #fff;
            background: #4f46e5;
            padding: 5px 10px;
            margin: 12px 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            page-break-after: avoid;
        }
        .cat-heading .cat-count { float: right; font-weight: normal; font-size: 9px; opacity: 0.85; }

        /* Active filters notice */
        .filters-notice {
            background: #eef2ff;
            border-left: 3px solid #4f46e5;
            padding: 4px 8px;
            margin-bottom: 8px;
            font-size: 7.5px;
            color: #4338ca;
        }
        .filters-notice strong { color: #312e81; text-transform: uppercase; letter-spacing: 0.5px; font-size: 7px; }
    </style>
</head>
<body>
<div class="page">

    {{-- Page header --}}
    <div class="page-header">
        <div class="page-header-left">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" class="company-logo" />
            @endif
            <span class="brand-name">{{ $brandName }}</span>
        </div>
        <div class="page-header-right">
            <div class="page-title">{{ $pageTitle ?? 'All Recipe Costs' }}</div>
            <div class="page-count">{{ $totalRecipes ?? 0 }} recipes | {{ now()->format('d M Y') }}</div>
        </div>
    </div>

    @if (! empty($activeFilters))
        <div class="filters-notice">
            <strong>Filtered:</strong> {{ implode('  ·  ', $activeFilters) }}
        </div>
    @endif

    @foreach ($groupedData as $categoryName => $recipesInCategory)
        <div class="cat-heading">
            {{ $categoryName }}
            <span class="cat-count">{{ count($recipesInCategory) }} {{ count($recipesInCategory) === 1 ? 'recipe' : 'recipes' }}</span>
        </div>

        @foreach ($recipesInCategory as $idx => $data)
        @php
            $recipe = $data['recipe'];
            $activePrices = collect($data['pricingAnalysis'])->filter(fn ($p) => $p['selling_price'] > 0);

            // Determine main selling price for summary strip
            $defaultPa = collect($data['pricingAnalysis'])->firstWhere('is_default', true);
            if (! $defaultPa || $defaultPa['selling_price'] <= 0) {
                $defaultPa = $activePrices->first();
            }
            $mainSp = $defaultPa ? $defaultPa['selling_price'] : $data['legacyPrice'];
            $mainFc = $mainSp > 0 ? ($data['costPerServing'] / $mainSp) * 100 : null;
            $mainGp = $mainSp > 0 ? $mainSp - $data['costPerServing'] : null;
            $mainMargin = $mainSp > 0 ? ($mainGp / $mainSp) * 100 : null;
            $mainMarkup = $data['costPerServing'] > 0 && $mainSp > 0 ? ($mainGp / $data['costPerServing']) * 100 : null;
        @endphp

        <div class="recipe-card">
            {{-- Title bar --}}
            <div class="card-title-bar">
                <span class="name">{{ $recipe->name }}</span>
                <span class="meta">
                    @if ($recipe->code) {{ $recipe->code }} | @endif
                    Yield: {{ rtrim(rtrim(number_format($data['yieldQty'], 2), '0'), '.') }} {{ $recipe->yieldUom?->abbreviation }}
                </span>
            </div>

            <div class="card-body">
                {{-- Info row --}}
                <div class="info-row">
                    @if ($recipe->category)
                        <span><span class="lbl">Category</span> {{ $recipe->category }}</span>
                    @endif
                    @if ($recipe->department)
                        <span><span class="lbl">Dept</span> {{ $recipe->department->name }}</span>
                    @endif
                    <span><span class="lbl">Cost/Srv</span> {{ number_format($data['costPerServing'], 4) }}</span>
                </div>

                {{-- Ingredients --}}
                <table class="ing-mini">
                    <thead>
                        <tr>
                            <th style="width: 16px;">#</th>
                            <th>Ingredient</th>
                            <th class="r" style="width: 45px;">Qty</th>
                            <th style="width: 30px;">UOM</th>
                            <th class="r" style="width: 35px;">Waste</th>
                            <th class="r" style="width: 55px;">Unit Cost</th>
                            <th class="r" style="width: 55px;">Line Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['lineData'] as $lidx => $ld)
                            <tr>
                                <td style="color: #bbb;">{{ $lidx + 1 }}</td>
                                <td>{{ $ld['ingredient'] }}</td>
                                <td class="r">{{ rtrim(rtrim(number_format($ld['quantity'], 4), '0'), '.') }}</td>
                                <td>{{ $ld['uom'] }}</td>
                                <td class="r" style="color: {{ $ld['waste_percentage'] > 0 ? '#ea580c' : '#ddd' }};">{{ $ld['waste_percentage'] > 0 ? number_format($ld['waste_percentage'], 1) . '%' : '—' }}</td>
                                <td class="r">{{ number_format($ld['unit_cost'], 4) }}</td>
                                <td class="r" style="font-weight: 600;">{{ number_format($ld['line_cost'], 4) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @if (! empty($data['packagingData']) || $data['extraCostTotal'] > 0 || ($data['totalTaxAll'] ?? 0) > 0)
                            <tr>
                                <td colspan="6" class="r">Ingredients</td>
                                <td class="r">{{ number_format($data['totalCost'], 2) }}</td>
                            </tr>
                        @endif
                        @if (! empty($data['packagingData']))
                            @foreach ($data['packagingData'] as $pd)
                                <tr class="sub">
                                    <td colspan="3" class="r" style="color: #6b7280; font-style: italic;">
                                        <span style="font-size: 6px; background: #e0e7ff; color: #4338ca; padding: 1px 3px; border-radius: 2px;">PKG</span>
                                        {{ $pd['ingredient'] }}
                                    </td>
                                    <td class="r">{{ rtrim(rtrim(number_format($pd['quantity'], 4), '0'), '.') }} {{ $pd['uom'] }}</td>
                                    <td class="r">{{ $pd['waste_percentage'] > 0 ? number_format($pd['waste_percentage'], 1) . '%' : '—' }}</td>
                                    <td class="r">{{ number_format($pd['unit_cost'], 4) }}</td>
                                    <td class="r">{{ number_format($pd['line_cost'], 4) }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="6" class="r">Packaging</td>
                                <td class="r">{{ number_format($data['packagingCost'], 2) }}</td>
                            </tr>
                        @endif
                        @if ($data['extraCostTotal'] > 0)
                            @foreach ($data['extraCosts'] as $ec)
                                <tr class="sub">
                                    <td colspan="6" class="r">{{ $ec['label'] ?? 'Extra' }}</td>
                                    <td class="r">{{ number_format(($ec['type'] ?? 'value') === 'percent' ? $data['totalCost'] * floatval($ec['amount'] ?? 0) / 100 : floatval($ec['amount'] ?? 0), 2) }}</td>
                                </tr>
                            @endforeach
                        @endif
                        @if (($data['totalTaxAll'] ?? 0) > 0)
                            <tr class="sub">
                                <td colspan="6" class="r" style="color: #6b7280;">Tax</td>
                                <td class="r" style="color: #6b7280;">{{ number_format($data['totalTaxAll'], 2) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="6" class="r">Total Cost @if (($data['totalTaxAll'] ?? 0) > 0)<span style="font-size: 6px; font-weight: normal; color: #888;">(incl. tax)</span>@endif</td>
                            <td class="r" style="font-size: 9px;">{{ number_format($data['grandCost'] + ($data['totalTaxAll'] ?? 0), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>

                {{-- Profitability (skip for prep items — not sold to customers) --}}
                @if (! ($isPrep ?? false) && $activePrices->count() > 1)
                    {{-- Multiple price classes: mini table --}}
                    <table class="class-mini">
                        <thead>
                            <tr>
                                <th>Price Class</th>
                                <th class="r">Price</th>
                                <th class="r">FC%</th>
                                <th class="r">Profit</th>
                                <th class="r">Margin</th>
                                <th class="r">Markup</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activePrices as $pa)
                                @php
                                    $gp = $pa['gross_profit'];
                                    $margin = $pa['selling_price'] > 0 ? ($gp / $pa['selling_price']) * 100 : 0;
                                    $markup = $data['costPerServing'] > 0 ? ($gp / $data['costPerServing']) * 100 : 0;
                                    $fcClass = match(true) {
                                        $pa['food_cost_pct'] <= 25 => 'c-green',
                                        $pa['food_cost_pct'] <= 35 => 'c-yellow',
                                        $pa['food_cost_pct'] <= 45 => 'c-orange',
                                        default                    => 'c-red',
                                    };
                                @endphp
                                <tr>
                                    <td class="bold">{{ $pa['name'] }}@if ($pa['is_default']) <span style="font-size: 6px; color: #aaa;">*</span>@endif</td>
                                    <td class="r bold">{{ number_format($pa['selling_price'], 2) }}</td>
                                    <td class="r bold {{ $fcClass }}">{{ number_format($pa['food_cost_pct'], 1) }}%</td>
                                    <td class="r" style="color: {{ $gp >= 0 ? '#16a34a' : '#dc2626' }};">{{ number_format($gp, 2) }}</td>
                                    <td class="r">{{ number_format($margin, 1) }}%</td>
                                    <td class="r">{{ number_format($markup, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif (! ($isPrep ?? false) && $mainSp > 0)
                    {{-- Single price: compact strip --}}
                    @php
                        $fcClass = match(true) {
                            $mainFc === null => '',
                            $mainFc <= 25    => 'c-green',
                            $mainFc <= 35    => 'c-yellow',
                            $mainFc <= 45    => 'c-orange',
                            default          => 'c-red',
                        };
                    @endphp
                    <div class="profit-strip">
                        <div class="ps-cell">
                            <div class="ps-label">Sell Price</div>
                            <div class="ps-value">{{ number_format($mainSp, 2) }}</div>
                            @if ($defaultPa && $defaultPa['name'] ?? null)
                                <div class="ps-sub">{{ $defaultPa['name'] }}</div>
                            @endif
                        </div>
                        <div class="ps-cell">
                            <div class="ps-label">Food Cost %</div>
                            <div class="ps-value {{ $fcClass }}">{{ number_format($mainFc, 1) }}%</div>
                        </div>
                        <div class="ps-cell">
                            <div class="ps-label">Gross Profit</div>
                            <div class="ps-value" style="color: {{ $mainGp >= 0 ? '#16a34a' : '#dc2626' }};">{{ number_format($mainGp, 2) }}</div>
                        </div>
                        <div class="ps-cell">
                            <div class="ps-label">Margin</div>
                            <div class="ps-value">{{ number_format($mainMargin, 1) }}%</div>
                        </div>
                        <div class="ps-cell">
                            <div class="ps-label">Markup</div>
                            <div class="ps-value">{{ number_format($mainMarkup, 1) }}%</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @endforeach
    @endforeach

    <div class="pdf-footer">
        Generated on {{ now()->format('d M Y, h:i A') }}{{ isset($exportedBy) ? ' by ' . $exportedBy : '' }} | {{ $brandName }} | Powered by Servora
    </div>

</div>
</body>
</html>
