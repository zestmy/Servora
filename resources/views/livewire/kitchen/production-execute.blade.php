<div>
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('kitchen.index') }}" class="text-gray-600 hover:text-gray-900 transition flex-shrink-0">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-600">
                <a href="{{ route('kitchen.index') }}" class="hover:underline">Kitchen</a>
                / Execute: {{ $order->order_number }}
            </p>
        </div>
        <div class="flex gap-2">
            <button wire:click="saveProgress" wire:loading.attr="disabled"
                    class="px-5 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                Save Progress
            </button>
            <button wire:click="complete"
                    wire:confirm="Complete this production order? This records the batch and adds the output to kitchen stock. Any line with a destination outlet is sent there straight away as a transfer."
                    class="px-5 py-2 bg-success-600 text-white text-sm font-medium rounded-lg hover:bg-success-700 transition">
                Complete Production
            </button>
        </div>
    </div>

    {{-- What Complete will do with the destination outlets on these lines.
         Stated up front so nobody discovers stock left the kitchen after the
         fact. --}}
    @php
        $bound = $order->lines->filter(fn ($l) => $l->to_outlet_id)
            ->groupBy(fn ($l) => $l->toOutlet?->name ?? 'Unknown outlet');
        $kitchenHasOutlet = (bool) $order->kitchen?->outlet_id;
    @endphp
    @if ($bound->isNotEmpty())
        <div class="mb-4 px-4 py-3 rounded-lg border {{ $kitchenHasOutlet ? 'bg-blue-50 border-blue-200 text-blue-800' : 'bg-warning-50 border-warning-200 text-warning-800' }}">
            @if ($kitchenHasOutlet)
                <p class="text-sm font-semibold">On Complete, these lines are sent out automatically</p>
                <ul class="mt-1 text-xs list-disc list-inside space-y-0.5">
                    @foreach ($bound as $outletName => $lines)
                        <li>{{ $outletName }} — {{ $lines->count() }} item(s), as one transfer</li>
                    @endforeach
                </ul>
                <p class="mt-1 text-xs">Everything is added to kitchen stock first, then what's bound for an outlet is deducted and transferred, so the movement stays on record.</p>
            @else
                <p class="text-sm font-semibold">Destination outlets can't be used</p>
                <p class="mt-1 text-xs">
                    {{ $order->kitchen?->name ?? 'This kitchen' }} has no linked outlet to transfer from, so the
                    output will stay in kitchen stock and you'll need to send it manually. Set a linked outlet in
                    Settings → Kitchen Management to enable automatic transfers.
                </p>
            @endif
        </div>
    @endif

    {{-- Order Info --}}
    <div class="card p-6 mb-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-600">Order Number</p>
                <p class="font-mono font-medium text-gray-700">{{ $order->order_number }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-600">Kitchen</p>
                <p class="font-medium text-gray-700">{{ $order->kitchen?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-600">Production Date</p>
                <p class="text-gray-700">{{ $order->production_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-600">Status</p>
                <span class="px-2 py-0.5 text-xs rounded-full font-medium bg-yellow-100 text-yellow-700">
                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>
            </div>
        </div>
        @if ($order->notes)
            <div class="mt-3 pt-3 border-t border-gray-100">
                <p class="text-xs text-gray-600">Notes</p>
                <p class="text-sm text-gray-600">{{ $order->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Validation errors --}}
    @if ($errors->any())
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            <p class="font-medium mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Production Lines --}}
    <div class="card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Production Lines</h3>
            <p class="text-xs text-gray-600 mt-0.5">Enter actual quantities produced for each line.</p>
        </div>

        {{-- Narrow screens: one card per line, big input with the unit right
             beside the number — nothing scrolls out of sight on a tablet. --}}
        <div class="md:hidden divide-y divide-gray-100">
            @foreach ($order->lines as $idx => $line)
                <div class="p-4" wire:key="xline-m-{{ $line->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-gray-800">{{ $line->recipe?->name ?? '-' }}</p>
                        <span class="text-xs text-gray-600 whitespace-nowrap">#{{ $idx + 1 }}</span>
                    </div>
                    <p class="text-xs text-gray-600 mt-0.5">
                        Planned: <span class="font-medium text-gray-600 tabular-nums">{{ rtrim(rtrim(number_format(floatval($line->planned_quantity), 4), '0'), '.') }} {{ $line->uom?->abbreviation }}</span>
                        @if ($line->toOutlet)
                            · for {{ $line->toOutlet->name }}
                        @endif
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <label class="text-xs font-medium text-gray-500 flex-shrink-0">Actual</label>
                        <input type="number" step="0.01" min="0" inputmode="decimal"
                               wire:model="actuals.{{ $idx }}"
                               class="flex-1 text-right text-lg font-semibold rounded-lg border-gray-300 py-2.5 focus:border-success-500 focus:ring-success-500 bg-success-50" />
                        <span class="text-sm font-medium text-gray-600 flex-shrink-0 w-12">{{ $line->uom?->abbreviation ?? '' }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Wide screens: the table --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-2 text-left w-8">#</th>
                        <th class="px-4 py-2 text-left">Recipe</th>
                        <th class="px-4 py-2 text-left w-20">UOM</th>
                        <th class="px-4 py-2 text-right w-28">Planned Qty</th>
                        <th class="px-4 py-2 text-right w-36">Actual Qty</th>
                        <th class="px-4 py-2 text-left">Destination</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($order->lines as $idx => $line)
                        <tr class="hover:bg-gray-50 transition" wire:key="xline-d-{{ $line->id }}">
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $line->recipe?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $line->uom?->abbreviation ?? '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                {{ rtrim(rtrim(number_format(floatval($line->planned_quantity), 4), '0'), '.') }}
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" step="0.01" min="0"
                                       wire:model="actuals.{{ $idx }}"
                                       class="w-full text-right rounded border-gray-300 text-sm focus:border-success-500 focus:ring-success-500 bg-success-50" />
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $line->toOutlet?->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <a href="{{ route('kitchen.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                &larr; Back to Kitchen
            </a>
            <div class="flex gap-2">
                <button wire:click="saveProgress" wire:loading.attr="disabled"
                        class="px-5 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                    Save Progress
                </button>
                <button wire:click="complete"
                        wire:confirm="Complete this production order? This records the batch and adds the output to kitchen stock. Any line with a destination outlet is sent there straight away as a transfer."
                        class="px-6 py-2 bg-success-600 text-white text-sm font-medium rounded-lg hover:bg-success-700 transition">
                    Complete Production
                </button>
            </div>
        </div>
    </div>
</div>
