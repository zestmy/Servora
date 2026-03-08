{{-- Revenue vs Purchases Trend (6 months) --}}
<h3 class="text-sm font-semibold text-gray-600 mb-4">Revenue vs Purchases (6 months)</h3>
@php
    $maxVal = max(collect($trendMonths)->max('revenue'), collect($trendMonths)->max('purchases'), 1);
@endphp
<div class="flex items-end gap-3 h-48">
    @foreach ($trendMonths as $m)
        <div class="flex-1 flex flex-col items-center gap-1">
            <div class="w-full flex gap-1 items-end" style="height: 160px">
                <div class="flex-1 bg-indigo-500 rounded-t-md relative group"
                     style="height: {{ $maxVal > 0 ? ($m['revenue'] / $maxVal * 100) : 0 }}%">
                    <div class="absolute -top-7 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 whitespace-nowrap transition pointer-events-none">
                        {{ number_format($m['revenue'], 0) }}
                    </div>
                </div>
                <div class="flex-1 bg-red-400 rounded-t-md relative group"
                     style="height: {{ $maxVal > 0 ? ($m['purchases'] / $maxVal * 100) : 0 }}%">
                    <div class="absolute -top-7 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 whitespace-nowrap transition pointer-events-none">
                        {{ number_format($m['purchases'], 0) }}
                    </div>
                </div>
            </div>
            <span class="text-xs text-gray-500 font-medium">{{ $m['label'] }}</span>
        </div>
    @endforeach
</div>
<div class="flex items-center gap-4 mt-4 text-xs text-gray-500">
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-indigo-500 rounded"></span> Revenue</span>
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 bg-red-400 rounded"></span> Purchases</span>
</div>
