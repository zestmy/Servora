<div>
    {{-- Top bar --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('sales.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400">
                <a href="{{ route('sales.index') }}" class="hover:underline">Sales</a>
                / {{ $recordId ? 'Edit Sales Record' : 'New Sales Entry' }}
            </p>
        </div>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Left: 2 cols --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Header card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Session Details</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="s_date" value="Sale Date *" />
                        <x-text-input id="s_date" wire:model.live="sale_date" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('sale_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="s_period" value="Meal Period *" />
                        <select id="s_period" wire:model.live="meal_period"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($mealPeriodOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('meal_period')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="s_pax" value="Pax (covers) *" />
                        <x-text-input id="s_pax" wire:model.live="pax" type="number" min="1" step="1"
                                      class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('pax')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="s_ref" value="Reference" />
                        <x-text-input id="s_ref" wire:model="reference_number" type="text"
                                      class="mt-1 block w-full" placeholder="e.g. Z-read #42" />
                        <x-input-error :messages="$errors->get('reference_number')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="s_notes" value="Notes" />
                    <textarea id="s_notes" wire:model="notes" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Optional notes…"></textarea>
                </div>

                {{-- Attachments --}}
                <div x-data="{ preview: null, previewType: null, previewName: null }">
                    <x-input-label value="Attachments" />
                    <p class="text-xs text-gray-400 mb-2">Attach images or PDFs as reference (e.g. Z-read receipts, POS reports). Max 5 MB each.</p>

                    {{-- Existing attachments --}}
                    @if (count($existingAttachments) > 0)
                        <div class="space-y-2 mb-3">
                            @foreach ($existingAttachments as $att)
                                <div class="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-2 border border-gray-100">
                                    @if ($att['is_image'])
                                        <img src="{{ $att['url'] }}" alt="{{ $att['file_name'] }}"
                                             class="w-10 h-10 object-cover rounded cursor-pointer hover:ring-2 hover:ring-indigo-300 transition"
                                             @click="preview = '{{ $att['url'] }}'; previewType = 'image'; previewName = '{{ addslashes($att['file_name']) }}'" />
                                    @else
                                        <div class="w-10 h-10 bg-red-50 rounded flex items-center justify-center flex-shrink-0 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition"
                                             @click="preview = '{{ $att['url'] }}'; previewType = 'pdf'; previewName = '{{ addslashes($att['file_name']) }}'">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <button type="button"
                                                @click="preview = '{{ $att['url'] }}'; previewType = '{{ $att['is_image'] ? 'image' : 'pdf' }}'; previewName = '{{ addslashes($att['file_name']) }}'"
                                                class="text-sm text-indigo-600 hover:underline truncate block text-left">{{ $att['file_name'] }}</button>
                                        <p class="text-xs text-gray-400">{{ $att['size'] }}</p>
                                    </div>
                                    <button type="button" wire:click="removeExistingAttachment({{ $att['id'] }})"
                                            wire:confirm="Remove this attachment?"
                                            class="text-red-400 hover:text-red-600 transition p-1 flex-shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- New attachment previews --}}
                    @if (count($newAttachments) > 0)
                        <div class="space-y-2 mb-3">
                            @foreach ($newAttachments as $idx => $file)
                                <div class="flex items-center gap-3 bg-indigo-50 rounded-lg px-3 py-2 border border-indigo-100">
                                    @if (str_starts_with($file->getMimeType(), 'image/'))
                                        <img src="{{ $file->temporaryUrl() }}" alt="Preview"
                                             class="w-10 h-10 object-cover rounded cursor-pointer hover:ring-2 hover:ring-indigo-300 transition"
                                             @click="preview = '{{ $file->temporaryUrl() }}'; previewType = 'image'; previewName = '{{ addslashes($file->getClientOriginalName()) }}'" />
                                    @else
                                        <div class="w-10 h-10 bg-red-50 rounded flex items-center justify-center flex-shrink-0 cursor-pointer hover:ring-2 hover:ring-indigo-300 transition"
                                             @click="preview = '{{ $file->temporaryUrl() }}'; previewType = 'pdf'; previewName = '{{ addslashes($file->getClientOriginalName()) }}'">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <button type="button"
                                                @click="preview = '{{ str_starts_with($file->getMimeType(), 'image/') ? $file->temporaryUrl() : $file->temporaryUrl() }}'; previewType = '{{ str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'pdf' }}'; previewName = '{{ addslashes($file->getClientOriginalName()) }}'"
                                                class="text-sm text-gray-700 hover:underline truncate block text-left">{{ $file->getClientOriginalName() }}</button>
                                        <p class="text-xs text-gray-400">{{ round($file->getSize() / 1024, 1) }} KB — ready to upload</p>
                                    </div>
                                    <button type="button" wire:click="removeNewAttachment({{ $idx }})"
                                            class="text-red-400 hover:text-red-600 transition p-1 flex-shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Upload input --}}
                    <label class="flex items-center justify-center gap-2 px-4 py-3 border-2 border-dashed border-gray-200 rounded-lg cursor-pointer hover:border-indigo-300 hover:bg-indigo-50/30 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span class="text-sm text-gray-500">Choose files</span>
                        <input type="file" wire:model="newAttachments" multiple accept="image/*,.pdf" class="hidden" />
                    </label>
                    <x-input-error :messages="$errors->get('newAttachments.*')" class="mt-1" />

                    <div wire:loading wire:target="newAttachments" class="mt-2 text-xs text-indigo-500">
                        Uploading...
                    </div>

                    {{-- Preview Lightbox --}}
                    <div x-show="preview" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="preview = null">
                        {{-- Backdrop --}}
                        <div class="fixed inset-0 bg-gray-900/70" @click="preview = null"></div>

                        {{-- Content --}}
                        <div class="relative z-10 bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden">
                            {{-- Header --}}
                            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 flex-shrink-0">
                                <p class="text-sm font-medium text-gray-700 truncate" x-text="previewName"></p>
                                <div class="flex items-center gap-2">
                                    <a :href="preview" target="_blank" class="text-gray-400 hover:text-indigo-600 transition p-1" title="Open in new tab">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                    <button type="button" @click="preview = null" class="text-gray-400 hover:text-gray-600 transition p-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Body --}}
                            <div class="flex-1 overflow-auto p-4 flex items-center justify-center bg-gray-50">
                                <template x-if="previewType === 'image'">
                                    <img :src="preview" :alt="previewName" class="max-w-full max-h-[75vh] object-contain rounded" />
                                </template>
                                <template x-if="previewType === 'pdf'">
                                    <iframe :src="preview" class="w-full h-[75vh] rounded border border-gray-200"></iframe>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Revenue by Sales Category card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Revenue by Sales Category</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Enter daily totals from your Z-read per category</p>
                </div>

                @if (count($lines) === 0)
                    <div class="px-6 py-10 text-center text-gray-400">
                        <p class="font-medium">No active sales categories</p>
                        <p class="text-xs mt-1">Add sales categories in
                            <a href="{{ route('settings.sales-categories') }}" class="text-indigo-500 underline">Settings → Sales Categories</a>.
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                                <tr>
                                    <th class="px-6 py-3 text-left">Category</th>
                                    <th class="px-6 py-3 text-right w-48">Revenue (RM)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($lines as $idx => $line)
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-3 h-3 rounded-full flex-shrink-0"
                                                     style="background-color: {{ $line['category_color'] }}"></div>
                                                <span class="font-medium text-gray-800">{{ $line['category_name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3">
                                            <input type="number" step="0.01" min="0"
                                                   wire:model.live.debounce.400ms="lines.{{ $idx }}.revenue"
                                                   class="w-full text-right rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                            <x-input-error :messages="$errors->get('lines.'.$idx.'.revenue')" class="mt-0.5" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                <tr>
                                    <td class="px-6 py-3 text-sm font-semibold text-gray-700">Grand Total</td>
                                    <td class="px-6 py-3 text-right font-bold text-gray-900 tabular-nums text-base">
                                        RM {{ number_format($grandTotal, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

                <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <a href="{{ route('sales.index') }}" class="text-sm text-gray-500 hover:text-gray-700 transition">
                        Cancel
                    </a>
                    <button wire:click="save"
                            class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save Sales Entry
                    </button>
                </div>
            </div>

        </div>

        {{-- Right: summary sidebar --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Session Summary</h3>

                {{-- Date / Period / Pax chips --}}
                <div class="flex flex-wrap gap-1.5 mb-4">
                    @if ($sale_date)
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full">
                            {{ \Carbon\Carbon::parse($sale_date)->format('d M Y') }}
                        </span>
                    @endif
                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded-full font-medium">
                        {{ $mealPeriodOptions[$meal_period] ?? ucfirst($meal_period) }}
                    </span>
                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">
                        {{ $pax }} pax
                    </span>
                </div>

                {{-- Category breakdown --}}
                @php $hasRevenue = collect($lines)->sum(fn($l) => floatval($l['revenue'])) > 0; @endphp
                @if ($hasRevenue)
                    <div class="space-y-2 mb-4">
                        @foreach ($lines as $line)
                            @php $rev = floatval($line['revenue']); @endphp
                            @if ($rev > 0)
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-0.5">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-2.5 h-2.5 rounded-full"
                                                 style="background-color: {{ $line['category_color'] }}"></div>
                                            <span class="text-gray-600">{{ $line['category_name'] }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-medium text-gray-800 tabular-nums">RM {{ number_format($rev, 2) }}</span>
                                            @if ($grandTotal > 0)
                                                <span class="text-gray-400 ml-1">{{ number_format($rev / $grandTotal * 100, 0) }}%</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($grandTotal > 0)
                                        <div class="w-full bg-gray-100 rounded-full h-1">
                                            <div class="h-1 rounded-full"
                                                 style="width: {{ number_format($rev / $grandTotal * 100, 1) }}%; background-color: {{ $line['category_color'] }}"></div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic mb-4">Enter revenue above to see breakdown.</p>
                @endif

                {{-- Total & avg check --}}
                <div class="border-t border-gray-100 pt-4 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Total Revenue</span>
                        <span class="text-xl font-bold text-gray-900 tabular-nums">RM {{ number_format($grandTotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-400">Avg Check / Pax</span>
                        <span class="text-sm font-semibold text-indigo-600 tabular-nums">
                            @if ($avgCheck !== null)
                                RM {{ number_format($avgCheck, 2) }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                </div>

                {{-- Save button --}}
                <button wire:click="save"
                        class="mt-5 w-full px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Save Sales Entry
                </button>
                <a href="{{ route('sales.index') }}"
                   class="mt-2 block text-center text-xs text-gray-400 hover:text-gray-600 transition">
                    Cancel
                </a>
            </div>
        </div>

    </div>
</div>
