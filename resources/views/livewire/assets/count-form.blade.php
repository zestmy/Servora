<div>
    @if (session()->has('info'))
        <div class="alert alert-info mb-4">{{ session('info') }}</div>
    @endif

    <x-page-header :title="$recordId ? 'Asset Count' : 'New Asset Count'" eyebrow="Assets">
        <x-slot:actions>
            <a href="{{ route('assets.records', ['tab' => 'counts']) }}" wire:navigate class="btn-secondary">Back</a>

            {{-- Only once the count exists: the sheet is generated from the
                 saved lines, so there is nothing to print from an unsaved one.
                 Save a draft first, then walk the outlet with the paper. --}}
            @if ($recordId)
                <a href="{{ route('assets.counts.count-sheet', $recordId) }}" target="_blank" class="btn-secondary">
                    <x-icon name="printer" size="h-4 w-4" />
                    <span class="hidden sm:inline">Print count sheet</span>
                </a>
            @endif

            @if (! $isEditable)
                <span class="badge-success">Completed</span>
                @canDo('assets.counts.reopen')
                    <button wire:click="reopen" class="btn-secondary">Reopen for editing</button>
                @endcanDo
            @else
                @canDo('assets.counts.record')
                    <button wire:click="save('save')" class="btn-secondary">Save draft</button>
                    {{-- No confirmation gate: completing is not destructive, and
                         the gate's dialog says "Confirm deletion". A completed
                         count can be reopened. --}}
                    <button wire:click="save('complete')" class="btn-primary">Complete count</button>
                @endcanDo
            @endif
        </x-slot:actions>
    </x-page-header>

    @error('lines') <div class="alert alert-danger mb-4">{{ $message }}</div> @enderror

    {{-- Header fields --}}
    <div class="card p-5 mb-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label" for="count-outlet">Outlet</label>
                @if ($hasOutletChoice && $isEditable)
                    <select id="count-outlet" wire:model.live="outlet_id" class="input">
                        <option value="">Choose…</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                @else
                    <p class="input bg-gray-50">{{ $outlets->firstWhere('id', $outlet_id)?->name ?? '—' }}</p>
                @endif
                @error('outlet_id') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="count-date">Count date</label>
                <input id="count-date" type="date" wire:model="count_date" class="input" @disabled(! $isEditable) />
                @error('count_date') <p class="error-text">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="count-ref">Reference</label>
                <input id="count-ref" type="text" wire:model="reference_number" class="input"
                       placeholder="Optional" @disabled(! $isEditable) />
            </div>

            <div>
                <label class="label" for="count-dept">Department</label>
                <select id="count-dept" wire:model="department_id" class="input" @disabled(! $isEditable)>
                    <option value="">Whole outlet</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
                <p class="help">Which part of the outlet was walked.</p>
            </div>

            <div class="sm:col-span-2 lg:col-span-4">
                <label class="label" for="count-notes">Notes</label>
                <textarea id="count-notes" wire:model="notes" rows="2" class="input" @disabled(! $isEditable)></textarea>
            </div>
        </div>
    </div>

    {{-- Building the sheet --}}
    @if ($isEditable)
        <div class="card p-5 mb-4">
            <x-card-title>Add assets to this count</x-card-title>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="relative flex-1">
                    <label class="label" for="count-search">Search</label>
                    <input id="count-search" type="text" wire:model.live.debounce.300ms="assetSearch"
                           placeholder="Name, code or brand…" class="input" />
                    @if (count($searchResults) > 0)
                        <div class="absolute z-20 mt-1 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg max-h-60">
                            @foreach ($searchResults as $item)
                                <button type="button" wire:click="addAsset({{ $item->id }})"
                                        class="flex w-full items-center justify-between gap-3 border-b border-gray-50 px-4 py-2.5 text-left text-sm last:border-0 hover:bg-brand-50">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        @if ($item->image_path)
                                            <img src="{{ $item->imageUrl() }}" alt="" loading="lazy"
                                                 class="h-8 w-8 flex-shrink-0 rounded-control border border-gray-200 object-cover" />
                                        @endif
                                        <div class="min-w-0">
                                            <span class="font-medium text-gray-700">{{ $item->name }}</span>
                                            @if ($item->code)<span class="ml-1 text-gray-600">({{ $item->code }})</span>@endif
                                            @if ($item->category)
                                                <span class="block text-xs text-gray-600">{{ $item->category->name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-600">{{ $item->uom?->abbreviation }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="sm:w-56">
                    <label class="label" for="count-load-cat">Load a whole category</label>
                    <select id="count-load-cat" wire:model="loadCategoryId" class="input">
                        <option value="">Everything active</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">
                                {{ $category->parent ? $category->parent->name . ' ▸ ' : '' }}{{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="button" wire:click="loadAll()" class="btn-secondary">Load</button>

                @if ($templates->isNotEmpty())
                    <div class="sm:w-56">
                        <label class="label" for="count-template">Load a template</label>
                        <select id="count-template" wire:model="selectedTemplateId" wire:change="loadTemplate" class="input">
                            <option value="">Choose a template…</option>
                            @foreach ($templates as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($lines)
                    <button type="button" wire:click="clearLines" class="btn-ghost"
                            data-confirm-delete="Clear every line off this count sheet.">
                        Clear sheet
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- Running totals, stuck to the top of the scroll area so they stay
         readable on the fortieth row rather than only beside the first. --}}
    <div class="sticky top-0 z-10 -mx-1 mb-2 bg-gray-50/95 px-1 py-2 backdrop-blur supports-[backdrop-filter]:bg-gray-50/80">
        <div class="card px-5 py-3">
            <div class="flex flex-wrap items-center gap-x-8 gap-y-2">
                <div>
                    <p class="stat-label">Lines</p>
                    <p class="text-sm font-semibold tabular-nums text-gray-800">
                        {{ $countedLines }} counted / {{ count($lines) }}
                    </p>
                </div>
                <div>
                    <p class="stat-label">Counted value</p>
                    <p class="text-sm font-semibold tabular-nums text-gray-800">{{ number_format($countedValue, 2) }}</p>
                </div>
                <div>
                    <p class="stat-label">Variance</p>
                    <p class="text-sm font-semibold tabular-nums {{ $varianceCost < 0 ? 'text-danger-600' : ($varianceCost > 0 ? 'text-success-700' : 'text-gray-800') }}">
                        {{ number_format($varianceCost, 2) }}
                    </p>
                </div>
                <label class="ml-auto inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model.live="hideSystemQty"
                           class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    Blind count
                </label>
            </div>
        </div>
    </div>

    {{-- The sheet --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[820px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left w-10">#</th>
                        <th class="px-4 py-3 text-left">Asset</th>
                        <th class="px-4 py-3 text-left w-36">Category</th>
                        @unless ($hideSystemQty)
                            <th class="px-4 py-3 text-right w-24">Expected</th>
                        @endunless
                        <th class="px-4 py-3 text-center w-28">Counted</th>
                        <th class="px-4 py-3 text-right w-24">Variance</th>
                        <th class="px-4 py-3 text-right w-28">Unit cost</th>
                        <th class="px-4 py-3 text-right w-28">Value</th>
                        @if ($isEditable)
                            <th class="px-4 py-3 w-12"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $i => $line)
                        @php
                            $counted  = ($line['counted_quantity'] === '' || $line['counted_quantity'] === null)
                                ? null : (float) $line['counted_quantity'];
                            $system   = (float) ($line['system_quantity'] ?? 0);
                            $cost     = (float) ($line['unit_cost'] ?? 0);
                            $variance = $counted === null ? null : $counted - $system;
                        @endphp
                        <tr wire:key="count-line-{{ $line['asset_id'] }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $i + 1 }}</td>

                            <td class="px-4 py-3">
                                {{-- The picture is the point of the column: somebody
                                     walking the outlet with forty smallwares on a sheet
                                     needs to tell two mixing bowls apart, and a name
                                     cannot do that. Tap to enlarge, because 40px cannot
                                     either once you are down to which of two knives. --}}
                                <div class="flex items-center gap-3" x-data="{ zoom: false }">
                                    @if ($line['image'] ?? null)
                                        <button type="button" @click="zoom = true" title="View larger"
                                                class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-control border border-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                            <img src="{{ $line['image'] }}" alt="" loading="lazy" class="h-full w-full object-cover" />
                                        </button>

                                        <template x-teleport="body">
                                            <div x-show="zoom" x-cloak @keydown.escape.window="zoom = false"
                                                 class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                                                <div class="fixed inset-0 bg-gray-900/70" @click="zoom = false"></div>
                                                <div class="relative max-h-full" @click.stop>
                                                    <img src="{{ $line['image'] }}" alt="{{ $line['asset_name'] }}"
                                                         class="max-h-[80vh] max-w-[90vw] rounded-panel bg-white object-contain shadow-e4" />
                                                    <p class="mt-3 text-center text-sm text-white/90">{{ $line['asset_name'] }}</p>
                                                </div>
                                            </div>
                                        </template>
                                    @else
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-control border border-gray-200 bg-gray-100">
                                            <x-icon name="building" size="h-4 w-4" class="text-gray-400" />
                                        </div>
                                    @endif

                                    <div class="min-w-0">
                                        <span class="font-medium text-gray-700">{{ $line['asset_name'] }}</span>
                                        @if ($line['code'])<span class="ml-1 text-gray-600">({{ $line['code'] }})</span>@endif
                                        <span class="ml-1 text-xs text-gray-600">{{ $line['uom'] }}</span>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-gray-600">{{ $line['category'] ?? '—' }}</td>

                            @unless ($hideSystemQty)
                                <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                                    {{ number_format($system, 2) }}
                                </td>
                            @endunless

                            <td class="px-4 py-3">
                                @if ($isEditable)
                                    <input type="number" step="0.01" min="0"
                                           wire:model.live.debounce.500ms="lines.{{ $i }}.counted_quantity"
                                           class="input text-center" placeholder="—" />
                                @else
                                    <div class="text-center tabular-nums">{{ number_format($counted ?? 0, 2) }}</div>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right tabular-nums {{ $variance === null ? 'text-gray-500' : ($variance < 0 ? 'text-danger-600 font-medium' : ($variance > 0 ? 'text-success-700' : 'text-gray-600')) }}">
                                {{ $variance === null ? '—' : number_format($variance, 2) }}
                            </td>

                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($cost, 2) }}</td>

                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                {{ $counted === null ? '—' : number_format($counted * $cost, 2) }}
                            </td>

                            @if ($isEditable)
                                <td class="px-4 py-3 text-center">
                                    <button type="button" wire:click="removeLine({{ $i }})"
                                            class="icon-btn icon-btn-danger" title="Remove">
                                        <x-icon name="close" size="h-4 w-4" />
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $hideSystemQty ? 7 : 8 }}" class="px-4 py-10">
                                <div class="empty-state">
                                    <p class="empty-title">No assets on this sheet yet</p>
                                    <p class="empty-body">
                                        Search for one, or load a whole category — the sheet is the order you walk
                                        the outlet in.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="help mt-4">
        Unit cost comes from the asset list and cannot be typed here — a count spends a price purchasing set.
        A line left blank was not counted and is dropped when the count is completed; a line you mean to be
        zero needs a typed 0, which shows as a variance you can explain.
    </p>
</div>
