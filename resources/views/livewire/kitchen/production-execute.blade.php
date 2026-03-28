<div>
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('kitchen.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('kitchen.index') }}" class="hover:underline">Kitchen</a>
                / Execute: {{ $order->order_number }}
            </p>
        </div>
        <button wire:click="complete"
                wire:confirm="Complete this production order? This will create production logs and outlet transfers."
                class="px-5 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
            Complete Production
        </button>
    </div>

    {{-- Order Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400">Order Number</p>
                <p class="font-mono font-medium text-gray-700">{{ $order->order_number }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Kitchen</p>
                <p class="font-medium text-gray-700">{{ $order->kitchen?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Production Date</p>
                <p class="text-gray-700">{{ $order->production_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Status</p>
                <span class="px-2 py-0.5 text-xs rounded-full font-medium bg-yellow-100 text-yellow-700">
                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>
            </div>
        </div>
        @if ($order->notes)
            <div class="mt-3 pt-3 border-t border-gray-100">
                <p class="text-xs text-gray-400">Notes</p>
                <p class="text-sm text-gray-600">{{ $order->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Validation errors --}}
    @if ($errors->any())
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            <p class="font-medium mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Production Lines --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Production Lines</h3>
            <p class="text-xs text-gray-400 mt-0.5">Enter actual quantities produced for each line.</p>
        </div>

        <div class="overflow-x-auto">
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
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $line->recipe?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $line->uom?->abbreviation ?? '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                {{ rtrim(rtrim(number_format(floatval($line->planned_quantity), 4), '0'), '.') }}
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" step="0.01" min="0"
                                       wire:model="actuals.{{ $idx }}"
                                       class="w-full text-right rounded border-gray-300 text-sm focus:border-green-500 focus:ring-green-500 bg-green-50" />
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
            <button wire:click="complete"
                    wire:confirm="Complete this production order? This will create production logs and outlet transfers."
                    class="px-6 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                Complete Production
            </button>
        </div>
    </div>
</div>
