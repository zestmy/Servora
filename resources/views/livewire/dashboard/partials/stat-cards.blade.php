{{-- Generic stat cards grid --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-{{ min(count($stats), 6) }} gap-4 mb-6">
    @foreach ($stats as $stat)
        @php
            $colorMap = [
                'indigo' => 'text-indigo-600',
                'green'  => 'text-green-600',
                'red'    => 'text-red-600',
                'amber'  => 'text-amber-600',
                'yellow' => 'text-yellow-600',
                'blue'   => 'text-blue-600',
                'purple' => 'text-purple-600',
                'gray'   => 'text-gray-900',
            ];
            $textColor = $colorMap[$stat['color'] ?? 'gray'] ?? 'text-gray-900';
        @endphp
        <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</div>
            <div class="mt-1 text-2xl font-bold {{ $textColor }}">{{ $stat['value'] }}</div>
        </div>
    @endforeach
</div>
