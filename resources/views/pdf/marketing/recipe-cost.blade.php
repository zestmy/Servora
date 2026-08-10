<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    {{-- dompdf: absolute positioning and simple tables only, no flex or grid. --}}
    <style>
        body  { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1    { font-size: 18px; margin: 0 0 2px; }
        .sub  { color: #6b7280; font-size: 10px; margin: 0 0 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th    { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .06em;
                color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 6px 4px; }
        td    { padding: 6px 4px; border-bottom: 1px solid #f3f4f6; }
        .r    { text-align: right; }
        .tot  { width: 46%; margin-left: 54%; }
        .tot td { border: 0; padding: 3px 4px; }
        .big  { font-size: 14px; font-weight: bold; }
        .foot { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 8px;
                color: #6b7280; font-size: 9px; }
    </style>
</head>
<body>
    <h1>{{ $recipeName }}</h1>
    <p class="sub">Recipe costing &middot; {{ now()->format('d M Y') }} &middot; {{ number_format($portions, 0) }} portion{{ $portions == 1 ? '' : 's' }}</p>

    <table>
        <thead>
            <tr>
                <th>Ingredient</th>
                <th class="r">Quantity</th>
                <th class="r">Unit price</th>
                <th class="r">Cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['name'] }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format($line['qty'], 2), '0'), '.') }} {{ $line['unit'] }}</td>
                    <td class="r">RM {{ number_format($line['price'], 2) }}</td>
                    <td class="r">RM {{ number_format($line['price'] * $line['qty'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="tot">
        <tr><td>Total cost</td><td class="r">RM {{ number_format($totals['cost'], 2) }}</td></tr>
        <tr><td>Cost per portion</td><td class="r">RM {{ number_format($totals['per_portion'], 2) }}</td></tr>
        <tr>
            <td class="big">Menu price at {{ rtrim(rtrim(number_format($targetFoodCostPct, 1), '0'), '.') }}%</td>
            <td class="r big">RM {{ number_format($totals['menu_price'], 2) }}</td>
        </tr>
    </table>

    <p class="foot">
        Ingredient prices are the median paid by kitchens running on Servora and move with the
        market. Suppliers are not identified. Built with the free calculator at servora.com.my.
    </p>
</body>
</html>
