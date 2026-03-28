<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('kitchen.index', ['tab' => 'requests']) }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('kitchen.index', ['tab' => 'requests']) }}" class="hover:underline">Kitchen / Prep Requests</a>
                / {{ $requestId ? $requestNumber : 'New Prep Request' }}
            </p>
        </div>
        @if ($isEditable)
        <div class="flex gap-2 flex-shrink-0">
            <button wire:click="save('submit')"
                    class="px-4 py-2 border border-blue-500 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition">
                Save & Submit
            </button>
            <button wire:click="save"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Save Draft
            </button>
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

    {{-- Header Details --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4 mb-4">
        <h3 class="text-sm font-semibold text-gray-700">Request Details</h3>

        {{-- Request Number (read-only) --}}
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Request Number</label>
            <p class="px-3 py-2 bg-gray-50 rounded-md border border-gray-200 text-sm font-mono text-gray-700">
                {{ $requestNumber }}
                @if ($requestId)
                    <span class="ml-2 text-xs font-sans px-2 py-0.5 rounded-full
                        {{ match($status) {
                            'draft'     => 'bg-gray-100 text-gray-600',
                            'submitted' => 'bg-yellow-100 text-yellow-700',
                            'approved'  => 'bg-blue-100 text-blue-700',
                            'fulfilled' => 'bg-green-100 text-green-700',
                            'cancelled' => 'bg-red-100 text-red-600',
                            default     => 'bg-gray-100 text-gray-500',
                        } }}">
                        {{ ucfirst($status) }}
                    </span>
                @endif
            </p>
        </div>

        {{-- Kitchen --}}
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Kitchen *</label>
            <select wire:model="kitchen_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">-- Select Kitchen --</option>
                @foreach ($kitchens as $k)
                    <option value="{{ $k->id }}">{{ $k->name }}{{ $k->code ? " ({$k->code})" : '' }}</option>
                @endforeach
            </select>
            @error('kitchen_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Needed Date --}}
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Needed Date *</label>
            <input type="date" wire:model="needed_date" class="w-full rounded-lg border-gray-300 text-sm" />
            @error('needed_date') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Notes --}}
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Notes</label>
            <textarea wire:model="notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm"
                      placeholder="Special requirements, urgency notes..."></textarea>
        </div>
    </div>

    {{-- Recipe Lines --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Request Lines</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ count($lines) }} item{{ count($lines) !== 1 ? 's' : '' }}</p>
            </div>
        </div>

        {{-- Recipe Search --}}
        @if ($isEditable)
        <div class="px-6 py-4 border-b border-gray-100">
            <div class="relative">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                </div>
                <input type="text"
                       wire:model.live.debounce.300ms="recipeSearch"
                       placeholder="Search prep recipes to add... (type at least 2 characters)"
                       class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            @if ($searchResults->isNotEmpty())
                <div class="mt-2 border border-gray-200 rounded-lg overflow-hidden divide-y divide-gray-100 shadow-sm">
                    @foreach ($searchResults as $recipe)
                        <button type="button"
                                wire:click="addRecipe({{ $recipe->id }})"
                                class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-indigo-50 transition text-left">
                            <div>
                                <span class="font-medium text-gray-800 text-sm">{{ $recipe->name }}</span>
                                @if ($recipe->code)
                                    <span class="ml-2 text-xs text-gray-400">{{ $recipe->code }}</span>
                                @endif
                            </div>
                            <div class="text-right flex-shrink-0 ml-4 text-xs text-gray-400">
                                <span>{{ $recipe->yieldUom?->abbreviation ?? '-' }}</span>
                                <span class="ml-2 text-indigo-400">+ Add</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            @elseif (strlen($recipeSearch) >= 2)
                <p class="mt-2 text-sm text-gray-400 text-center py-2">No prep recipes found for "{{ $recipeSearch }}".</p>
            @endif

            @error('lines') <p class="text-xs text-red-500 mt-2">{{ $message }}</p> @enderror
        </div>
        @endif

        {{-- Lines table --}}
        @if (count($lines))
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-4 py-2 text-left w-8">#</th>
                            <th class="px-4 py-2 text-left">Recipe</th>
                            <th class="px-4 py-2 text-right w-28">Quantity</th>
                            <th class="px-4 py-2 text-left w-20">UOM</th>
                            @if ($isEditable)
                            <th class="px-4 py-2 w-10"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($lines as $idx => $line)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $idx + 1 }}</td>
                                <td class="px-4 py-2 font-medium text-gray-800">{{ $line['recipe_name'] }}</td>
                                <td class="px-4 py-2">
                                    @if ($isEditable)
                                        <input type="number" step="0.01" min="0.01"
                                               wire:model.lazy="lines.{{ $idx }}.requested_quantity"
                                               class="w-full text-right rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    @else
                                        <p class="text-right tabular-nums text-gray-700">{{ rtrim(rtrim(number_format(floatval($line['requested_quantity']), 4), '0'), '.') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-600">{{ $line['uom_name'] }}</td>
                                @if ($isEditable)
                                <td class="px-4 py-2 text-center">
                                    <button type="button" wire:click="removeLine({{ $idx }})"
                                            class="text-red-400 hover:text-red-600 transition">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-400">
                <p class="font-medium">No lines added yet</p>
                <p class="text-xs mt-1">Use the search above to add prep recipes to this request.</p>
            </div>
        @endif

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <a href="{{ route('kitchen.index', ['tab' => 'requests']) }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                &larr; Back
            </a>
            @if ($isEditable)
                <div class="flex gap-2">
                    <button wire:click="save('submit')"
                            class="px-4 py-2 border border-blue-500 text-blue-600 text-sm font-medium rounded-lg hover:bg-blue-50 transition">
                        Save & Submit
                    </button>
                    <button wire:click="save"
                            class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save Draft
                    </button>
                </div>
            @else
                <p class="text-xs text-gray-400 italic">This request is read-only (status: {{ ucfirst($status) }}).</p>
            @endif
        </div>
    </div>
</div>
