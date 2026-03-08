{{-- Purchasing Dashboard — PO/DO/GRN focused --}}

@include('livewire.dashboard.partials.alerts')
@include('livewire.dashboard.partials.stat-cards')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Quick Actions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-600 mb-4">Quick Actions</h3>
        <div class="space-y-3">
            <a href="{{ route('purchasing.index', ['tab' => 'po', 'statusFilter' => 'submitted']) }}" class="flex items-center justify-between p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                <div class="flex items-center gap-3">
                    <span class="text-lg">📥</span>
                    <span class="font-medium text-gray-700">Review Submitted POs</span>
                </div>
                @if (($stats[0]['value'] ?? 0) > 0)
                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-bold rounded-full">{{ $stats[0]['value'] }}</span>
                @endif
            </a>
            <a href="{{ route('purchasing.index', ['tab' => 'do']) }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                <span class="text-lg">🚚</span>
                <span class="font-medium text-gray-700">View Delivery Orders</span>
            </a>
            <a href="{{ route('purchasing.index', ['tab' => 'grn']) }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 transition text-sm">
                <span class="text-lg">📋</span>
                <span class="font-medium text-gray-700">View Goods Received</span>
            </a>
        </div>
    </div>

    {{-- Workflow Summary --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold text-gray-600 mb-4">Workflow Pipeline</h3>
        <div class="space-y-4">
            @php
                $pipeline = [
                    ['label' => 'POs Submitted', 'value' => $stats[0]['value'] ?? 0, 'color' => 'bg-indigo-500'],
                    ['label' => 'POs Approved', 'value' => $stats[1]['value'] ?? 0, 'color' => 'bg-purple-500'],
                    ['label' => 'DOs Pending Delivery', 'value' => $stats[2]['value'] ?? 0, 'color' => 'bg-yellow-500'],
                    ['label' => 'GRNs Pending Receipt', 'value' => $stats[3]['value'] ?? 0, 'color' => 'bg-amber-500'],
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
