<div style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p style="margin: 0 0 16px;">Here is the costing for <strong>{{ $recipeName }}</strong>, attached as a PDF.</p>

    <p style="margin: 0 0 8px;">
        Cost to make: <strong>RM {{ number_format($totals['cost'], 2) }}</strong><br>
        Per portion: <strong>RM {{ number_format($totals['per_portion'], 2) }}</strong><br>
        Menu price at {{ rtrim(rtrim(number_format($targetFoodCostPct, 1), '0'), '.') }}% food cost:
        <strong>RM {{ number_format($totals['menu_price'], 2) }}</strong>
    </p>

    <p style="margin: 16px 0 0; color: #6b7280; font-size: 13px;">
        Prices are the median paid by kitchens running on Servora, so they move with the market
        rather than sitting in a spreadsheet. Costing every recipe you sell, automatically, is
        what Servora does — <a href="https://servora.com.my">have a look</a>.
    </p>
</div>
