@extends('pdf.layout')

@section('title', $pageTitle ?? 'Recipe Cost Summary')

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
            <div class="doc-title">{{ $pageTitle ?? 'Recipe Cost Summary' }}</div>
            <div class="doc-number">{{ count($summaryRows) }} Recipes</div>
        </div>
    </div>

    @if (! empty($activeFilters))
        <div style="background: #eef2ff; border-left: 3px solid #4f46e5; padding: 5px 10px; margin-bottom: 8px; font-size: 9pt; color: #4338ca;">
            <strong style="text-transform: uppercase; letter-spacing: 0.5px; font-size: 8pt;">Filtered:</strong>
            {{ implode('  ·  ', $activeFilters) }}
        </div>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th style="width: 25px;">#</th>
                <th>Recipe</th>
                <th>Category</th>
                <th class="right" style="width: 55px;">Ingredients</th>
                <th class="right" style="width: 50px;">Packaging</th>
                <th class="right" style="width: 45px;">Tax</th>
                <th class="right" style="width: 65px;">Total Cost</th>
                <th class="right" style="width: 60px;">Cost/Srv</th>
                @if ($priceClasses->isNotEmpty())
                    @foreach ($priceClasses as $pc)
                        <th class="right" style="width: 55px;">{{ $pc->name }}</th>
                        <th class="right" style="width: 45px;">FC%</th>
                    @endforeach
                @else
                    <th class="right" style="width: 60px;">Price</th>
                    <th class="right" style="width: 45px;">FC%</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php $currentCategory = null; @endphp
            @foreach ($summaryRows as $idx => $row)
                @if ($row['category'] !== $currentCategory)
                    @php $currentCategory = $row['category']; @endphp
                    @if ($currentCategory)
                        <tr>
                            <td colspan="{{ $priceClasses->isNotEmpty() ? 8 + ($priceClasses->count() * 2) : 10 }}"
                                style="background: #f3f4f6; font-weight: bold; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; padding: 4px 6px; color: #374151;">
                                {{ $currentCategory }}
                            </td>
                        </tr>
                    @endif
                @endif
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>
                        <span style="font-weight: 600;">{{ $row['name'] }}</span>
                        @if ($row['code'])
                            <span style="font-size: 7px; color: #888;"> {{ $row['code'] }}</span>
                        @endif
                    </td>
                    <td style="font-size: 8px; color: #666;">{{ $row['category'] }}</td>
                    <td class="right">{{ number_format($row['ingredient_cost'] ?? 0, 2) }}</td>
                    <td class="right" style="color: {{ ($row['packaging_cost'] ?? 0) > 0 ? '#4338ca' : '#ccc' }};">
                        {{ ($row['packaging_cost'] ?? 0) > 0 ? number_format($row['packaging_cost'], 2) : '—' }}
                    </td>
                    <td class="right" style="color: {{ ($row['tax'] ?? 0) > 0 ? '#6b7280' : '#ccc' }};">
                        {{ ($row['tax'] ?? 0) > 0 ? number_format($row['tax'], 2) : '—' }}
                    </td>
                    <td class="right" style="font-weight: 600;">{{ number_format($row['total_cost'], 2) }}</td>
                    <td class="right">{{ number_format($row['cost_per_serving'], 4) }}</td>
                    @if ($priceClasses->isNotEmpty())
                        @foreach ($priceClasses as $pc)
                            @php $cp = $row['class_prices'][$pc->id] ?? ['selling_price' => 0, 'food_cost_pct' => null]; @endphp
                            <td class="right">{{ $cp['selling_price'] > 0 ? number_format($cp['selling_price'], 2) : '—' }}</td>
                            <td class="right" style="font-weight: bold; {{ $cp['food_cost_pct'] !== null ? 'color: ' . ($cp['food_cost_pct'] <= 35 ? '#16a34a' : ($cp['food_cost_pct'] <= 45 ? '#ea580c' : '#dc2626')) : 'color: #ccc;' }}">
                                {{ $cp['food_cost_pct'] !== null ? number_format($cp['food_cost_pct'], 1) . '%' : '—' }}
                            </td>
                        @endforeach
                    @else
                        <td class="right">{{ $row['legacy_price'] > 0 ? number_format($row['legacy_price'], 2) : '—' }}</td>
                        <td class="right" style="font-weight: bold; {{ $row['legacy_food_cost_pct'] !== null ? 'color: ' . ($row['legacy_food_cost_pct'] <= 35 ? '#16a34a' : ($row['legacy_food_cost_pct'] <= 45 ? '#ea580c' : '#dc2626')) : 'color: #ccc;' }}">
                            {{ $row['legacy_food_cost_pct'] !== null ? number_format($row['legacy_food_cost_pct'], 1) . '%' : '—' }}
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 10px; font-size: 8.5pt; color: #888;">
        <strong>Food Cost Guide:</strong>
        <span style="color: #16a34a; font-weight: bold;">&bull; &le;35% Good</span> &nbsp;
        <span style="color: #ea580c; font-weight: bold;">&bull; 35-45% High</span> &nbsp;
        <span style="color: #dc2626; font-weight: bold;">&bull; &gt;45% Review</span>
    </div>
@endsection
