{{-- Purchasing Dashboard — PO/DO/GRN focused --}}

@include('livewire.dashboard.partials.alerts')
@include('livewire.dashboard.partials.stat-cards')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Quick Actions --}}
    <div class="card p-6">
        @include('livewire.dashboard.partials.quick-actions', ['actions' => [
            ['route' => 'purchasing.index', 'params' => ['tab' => 'po', 'statusFilter' => 'submitted'],
             'icon'  => 'inbox',     'label' => 'Review submitted POs', 'count' => $stats[0]['value'] ?? null],
            ['route' => 'purchasing.index', 'params' => ['tab' => 'do'],
             'icon'  => 'truck',     'label' => 'View delivery orders'],
            ['route' => 'purchasing.index', 'params' => ['tab' => 'grn'],
             'icon'  => 'clipboard', 'label' => 'View goods received'],
        ]])
    </div>

    {{-- Workflow Summary --}}
    <div class="card p-6">
        <h3 class="text-sm font-semibold text-gray-600 mb-4">Workflow Pipeline</h3>
        <div class="space-y-4">
            @php
                $pipeline = [
                    ['label' => 'POs Submitted', 'value' => $stats[0]['value'] ?? 0, 'color' => 'bg-brand-500'],
                    ['label' => 'POs Approved', 'value' => $stats[1]['value'] ?? 0, 'color' => 'bg-purple-500'],
                    ['label' => 'DOs Pending Delivery', 'value' => $stats[2]['value'] ?? 0, 'color' => 'bg-yellow-500'],
                    ['label' => 'GRNs Pending Receipt', 'value' => $stats[3]['value'] ?? 0, 'color' => 'bg-warning-500'],
                ];
                $maxPipeline = max(collect($pipeline)->max('value'), 1);
            @endphp
            @foreach ($pipeline as $step)
                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="text-gray-600">{{ $step['label'] }}</span>
                        <span class="font-bold text-gray-800">{{ $step['value'] }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div class="h-2.5 rounded-full {{ $step['color'] }}" style="width: {{ $maxPipeline > 0 ? min(($step['value'] / $maxPipeline) * 100, 100) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
