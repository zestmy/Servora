<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ingredients List</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @@page { margin: 15mm 12mm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 7.5px; color: #1a1a1a; line-height: 1.3; padding: 5mm; }

        .header { display: table; width: 100%; margin-bottom: 8px; border-bottom: 1.5px solid #2d3748; padding-bottom: 6px; }
        .header-left { display: table-cell; vertical-align: middle; width: 60%; }
        .header-right { display: table-cell; vertical-align: middle; width: 40%; text-align: right; }
        .logo { max-height: 30px; max-width: 100px; margin-right: 6px; vertical-align: middle; }
        .brand { font-size: 10px; font-weight: bold; vertical-align: middle; }
        .title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #666; }
        .meta { font-size: 6.5px; color: #999; margin-top: 2px; }

        .filters { font-size: 7px; color: #555; margin-bottom: 6px; padding: 3px 6px; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 2px; }
        .filters strong { color: #2d3748; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #2d3748; color: #fff; padding: 3px 5px;
            font-size: 6.5px; text-transform: uppercase; letter-spacing: 0.5px;
            text-align: left; font-weight: 600;
        }
        thead th.r { text-align: right; }
        thead th.c { text-align: center; }
        tbody td {
            padding: 2.5px 5px; font-size: 7.5px;
            border-bottom: 1px solid #edf2f7;
        }
        tbody td.r { text-align: right; font-variant-numeric: tabular-nums; }
        tbody td.c { text-align: center; }
        tbody tr:nth-child(even) { background: #fafbfc; }

        .cat-row td { background: #edf2f7; font-weight: bold; font-size: 7.5px; padding: 3px 5px; color: #2d3748; }

        .supplier-list { font-size: 6.5px; color: #555; }
        .supplier-pref { color: #2563eb; font-weight: 600; }
        .supplier-add { color: #888; }

        .badge-active { color: #16a34a; font-weight: 600; }
        .badge-inactive { color: #dc2626; font-weight: 600; }

        .footer { margin-top: 8px; padding-top: 4px; border-top: 1px solid #e2e8f0; font-size: 6.5px; color: #999; text-align: right; }
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
            <div class="title">Ingredients List</div>
            <div class="meta">{{ now()->format('d M Y, h:i A') }} · {{ $ingredients->count() }} items</div>
        </div>
    </div>

    @if (! empty($filters))
        <div class="filters">
            <strong>Filters:</strong> {{ implode(' · ', $filters) }}
        </div>
    @endif

    @php
        $grouped = $ingredients->groupBy(function ($ing) {
            $ic = $ing->ingredientCategory;
            $root = $ic?->parent ?? $ic;
            return $root?->name ?? 'Uncategorised';
        });
    @endphp

    <table>
        <thead>
            <tr>
                <th style="width: 14px;">#</th>
                <th>Name</th>
                <th style="width: 45px;">Code</th>
                <th style="width: 50px;">Category</th>
                <th style="width: 30px;" class="c">UOM</th>
                <th style="width: 35px;" class="c">Recipe</th>
                <th style="width: 40px;" class="r">Pack</th>
                <th style="width: 48px;" class="r">Price</th>
                <th style="width: 48px;" class="r">Cost/Unit</th>
                <th style="width: 28px;" class="r">Yield</th>
                <th style="width: 25px;" class="c">Status</th>
                <th>Suppliers</th>
            </tr>
        </thead>
        <tbody>
            @php $n = 0; @endphp
            @foreach ($grouped as $catName => $catIngredients)
                <tr class="cat-row">
                    <td colspan="12">{{ $catName }} ({{ $catIngredients->count() }})</td>
                </tr>
                @foreach ($catIngredients as $ing)
                    @php
                        $n++;
                        $preferred = $ing->suppliers->firstWhere('pivot.is_preferred', true);
                        $additional = $ing->suppliers->where('pivot.is_preferred', false);
                        $ic = $ing->ingredientCategory;
                        $subCat = ($ic && $ic->parent) ? $ic->name : null;
                    @endphp
                    <tr>
                        <td>{{ $n }}</td>
                        <td style="font-weight: 600;">{{ $ing->name }}</td>
                        <td>{{ $ing->code ?? '' }}</td>
                        <td>{{ $subCat ?? '' }}</td>
                        <td class="c">{{ $ing->baseUom?->abbreviation ?? '' }}</td>
                        <td class="c">{{ $ing->recipeUom?->abbreviation ?? '' }}</td>
                        <td class="r">{{ rtrim(rtrim(number_format(floatval($ing->pack_size ?? 1), 2), '0'), '.') }}</td>
                        <td class="r">{{ number_format($ing->purchase_price, 2) }}</td>
                        <td class="r">{{ number_format($ing->current_cost, 4) }}</td>
                        <td class="r">{{ $ing->yield_percent }}%</td>
                        <td class="c">
                            @if ($ing->is_active)
                                <span class="badge-active">Y</span>
                            @else
                                <span class="badge-inactive">N</span>
                            @endif
                        </td>
                        <td class="supplier-list">
                            @if ($preferred)
                                <span class="supplier-pref">{{ $preferred->name }}</span>
                            @endif
                            @if ($additional->isNotEmpty())
                                @if ($preferred), @endif
                                <span class="supplier-add">{{ $additional->pluck('name')->join(', ') }}</span>
                            @endif
                            @if ($ing->suppliers->isEmpty())
                                <span style="color: #ccc;">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generated by Servora · {{ $brandName }}
    </div>

</body>
</html>
