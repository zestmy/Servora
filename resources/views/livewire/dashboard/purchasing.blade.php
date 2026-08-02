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
        <h2 class="mb-4 text-sm font-semibold text-gray-900">Workflow pipeline</h2>

        {{-- These four are ordered stages of one process, not four unrelated
             things, so they take one hue stepping darker along the pipeline
             rather than teal / purple / yellow / amber. Sequential data gets a
             sequential ramp; the arbitrary hues implied a categorical
             difference that is not there.

             Position in the list already carries the order, so the ramp is
             reinforcing it rather than being the only cue. --}}
        <div class="space-y-4">
            @php
                $pipeline = [
                    ['label' => 'POs submitted',        'value' => $stats[0]['value'] ?? 0, 'bar' => 'bg-brand-300'],
                    ['label' => 'POs approved',         'value' => $stats[1]['value'] ?? 0, 'bar' => 'bg-brand-500'],
                    ['label' => 'DOs pending delivery', 'value' => $stats[2]['value'] ?? 0, 'bar' => 'bg-brand-600'],
                    ['label' => 'GRNs pending receipt', 'value' => $stats[3]['value'] ?? 0, 'bar' => 'bg-brand-800'],
                ];
                $maxPipeline = max(collect($pipeline)->max('value'), 1);
            @endphp

            @foreach ($pipeline as $step)
                @php $pct = $maxPipeline > 0 ? min(($step['value'] / $maxPipeline) * 100, 100) : 0; @endphp
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="text-gray-700">{{ $step['label'] }}</span>
                        <span class="font-semibold tabular-nums text-gray-900">{{ $step['value'] }}</span>
                    </div>
                    {{-- The count beside the label is the real readout, so the
                         bar is presentational and stays out of the a11y tree. --}}
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                        <div class="h-2.5 rounded-full {{ $step['bar'] }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
