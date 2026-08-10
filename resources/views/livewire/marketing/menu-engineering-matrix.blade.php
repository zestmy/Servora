<div class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="text-center">
        <p class="page-eyebrow">Free tool</p>
        <h1 class="display-2 mt-2">Menu Engineering Matrix</h1>
        <p class="mx-auto mt-3 max-w-2xl text-gray-600">
            Put every dish against two lines — how often it sells, and how much it leaves behind —
            and get four groups with four different instructions.
        </p>
    </div>

    <div class="card mt-8 p-6">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Dish</th>
                        <th class="px-4 py-3 text-right w-32">Cost</th>
                        <th class="px-4 py-3 text-right w-32">Price</th>
                        <th class="px-4 py-3 text-right w-32">Sold</th>
                        <th class="px-3 py-3 w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $i => $item)
                        <tr wire:key="row-{{ $i }}">
                            <td class="px-4 py-2">
                                <input type="text" wire:model.live.debounce.500ms="items.{{ $i }}.name"
                                       class="input w-full" placeholder="Dish name" />
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="items.{{ $i }}.cost"
                                       class="input w-full text-right tabular-nums" />
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="items.{{ $i }}.price"
                                       class="input w-full text-right tabular-nums" />
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" min="0" step="1" wire:model.live.debounce.500ms="items.{{ $i }}.sold"
                                       class="input w-full text-right tabular-nums" />
                            </td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" wire:click="removeRow({{ $i }})"
                                        aria-label="Remove row {{ $i + 1 }}"
                                        class="icon-btn text-danger-400 hover:text-danger-600">&times;</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="button" wire:click="addRow" class="btn-secondary mt-4">+ Add a dish</button>

        @if (! $analysis)
            <div class="empty-state mt-6">
                <p class="font-medium text-gray-800">Fill in at least three dishes</p>
                <p class="help mt-1">
                    One dish has nothing to be compared against and two is a coin toss —
                    the method needs a menu. Name, price and units sold are the minimum.
                </p>
            </div>
        @endif
    </div>

    @if ($analysis)
        {{-- The two lines everything is judged against, stated before the
             verdicts. A matrix that will not show its thresholds is a matrix
             asking to be trusted rather than read. --}}
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="stat">
                <p class="stat-label">Average profit per dish sold</p>
                <p class="stat-value tabular-nums">RM {{ number_format($analysis['average'], 2) }}</p>
                <p class="text-xs text-gray-600 mt-1">weighted by how many actually sold</p>
            </div>
            <div class="stat">
                <p class="stat-label">Popularity line</p>
                <p class="stat-value tabular-nums">{{ number_format($analysis['popularity_line'], 1) }}%</p>
                <p class="text-xs text-gray-600 mt-1">70% of an equal share</p>
            </div>
            <div class="stat">
                <p class="stat-label">Total gross profit</p>
                <p class="stat-value tabular-nums">RM {{ number_format($analysis['total_profit'], 2) }}</p>
                <p class="text-xs text-gray-600 mt-1">{{ number_format($analysis['total_sold']) }} dishes</p>
            </div>
        </div>

        {{-- Quadrants, in the order they are usually acted on. --}}
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @foreach (['star' => 'success', 'plowhorse' => 'warning', 'puzzle' => 'info', 'dog' => 'danger'] as $key => $tone)
                @php $group = $analysis['grouped'][$key] ?? collect(); @endphp
                <div class="card p-5">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="text-base font-semibold text-gray-900">
                            {{ $analysis['quadrants'][$key]['name'] }}
                        </h2>
                        <span class="badge-{{ $tone }}">{{ $group->count() }}</span>
                    </div>
                    <p class="help mt-1">{{ $analysis['quadrants'][$key]['advice'] }}</p>

                    @if ($group->isNotEmpty())
                        <ul class="mt-3 space-y-1.5">
                            @foreach ($group as $item)
                                <li class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="font-medium text-gray-800">{{ $item['name'] }}</span>
                                    <span class="whitespace-nowrap tabular-nums text-gray-600">
                                        RM {{ number_format($item['contribution'], 2) }} &times; {{ number_format($item['sold']) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-3 text-sm text-gray-500">Nothing here — which is good news for this one.</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left">Dish</th>
                            <th class="px-4 py-3 text-right">Profit each</th>
                            <th class="px-4 py-3 text-right">Sold</th>
                            <th class="px-4 py-3 text-right">Share</th>
                            <th class="px-4 py-3 text-right">Total profit</th>
                            <th class="px-4 py-3 text-left">Verdict</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($analysis['items'] as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $item['name'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">RM {{ number_format($item['contribution'], 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($item['sold']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($item['share'], 1) }}%</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium">RM {{ number_format($item['total'], 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="badge-{{ ['star' => 'success', 'plowhorse' => 'warning', 'puzzle' => 'info', 'dog' => 'danger'][$item['quadrant']] }}">
                                        {{ $item['label'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <p class="mt-6 text-center text-sm text-gray-600">
        Don't know what your dishes cost?
        <a href="{{ route('tools.recipe-cost') }}" class="text-brand-600 underline">Cost one from real ingredient prices</a>
        &mdash; or let <a href="{{ route('saas.register') }}" class="text-brand-600 underline">Servora</a>
        keep this matrix current from your own sales.
    </p>
</div>
