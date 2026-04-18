<div>
    {{-- Populate the shared ingredient list before any picker initialises. --}}
    <div x-init="window.__pwIngredientsList = @js(array_values($ingredients))"
         wire:key="pw-ingredients-init" class="hidden"></div>

    @if (session()->has('success'))
        <div wire:key="flash-ok-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">{{ session('error') }}</div>
    @endif

    <div class="flex items-center gap-3 mb-4">
        <a href="{{ route('ingredients.review-documents') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400">Inventory &amp; Recipes / Review Documents / Review</p>
            <h2 class="text-lg font-semibold text-gray-800 mt-0.5">Review Document</h2>
        </div>
    </div>

    @if ($imported)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-2">Document imported</h3>
            <p class="text-sm text-gray-500 mb-4">Supplier: <strong>{{ $supplierName }}</strong> · Effective <strong>{{ $effectiveDate }}</strong></p>
            <div class="flex flex-wrap items-center justify-center gap-6 text-sm mb-6">
                @if ($linkedCount > 0)
                    <div><p class="text-2xl font-bold text-green-600">{{ $linkedCount }}</p><p class="text-xs text-gray-500">Links added / updated</p></div>
                @endif
                @if ($createdCount > 0)
                    <div><p class="text-2xl font-bold text-indigo-600">{{ $createdCount }}</p><p class="text-xs text-gray-500">New ingredients</p></div>
                @endif
                @if ($priceChangedCount > 0)
                    <div><p class="text-2xl font-bold text-amber-600">{{ $priceChangedCount }}</p><p class="text-xs text-gray-500">Price changes logged</p></div>
                @endif
                @if ($skippedCount > 0)
                    <div><p class="text-2xl font-bold text-gray-400">{{ $skippedCount }}</p><p class="text-xs text-gray-500">Skipped</p></div>
                @endif
            </div>
            <div class="flex items-center justify-center gap-3">
                <a href="{{ route('ingredients.review-documents') }}" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">Back to Review</a>
                <a href="{{ route('ingredients.scan-document') }}" class="px-5 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">Scan another</a>
            </div>
        </div>
    @else
        {{-- Supplier + effective date --}}
        <div class="mb-4 px-4 py-4 bg-white rounded-xl shadow-sm border border-gray-100 space-y-3">
            @if ($detectedSupplierName)
                <div class="px-3 py-2 bg-indigo-50 border border-indigo-200 text-indigo-800 text-xs rounded-lg">
                    AI detected supplier: <strong>{{ $detectedSupplierName }}</strong>
                    @if ($supplierId && $supplierName)
                        — matched to your existing supplier <strong>{{ $supplierName }}</strong>.
                    @elseif ($supplierMode === 'new')
                        — no match yet. Create below or pick an existing one.
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <x-input-label value="Supplier" />
                    <div class="mt-1 flex items-center gap-2 text-xs">
                        <button type="button" wire:click="$set('supplierMode','existing')"
                                class="px-2.5 py-1 rounded-md border {{ $supplierMode === 'existing' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500 hover:bg-gray-50' }}">Use existing</button>
                        <button type="button" wire:click="$set('supplierMode','new')"
                                class="px-2.5 py-1 rounded-md border {{ $supplierMode === 'new' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500 hover:bg-gray-50' }}">Create new</button>
                    </div>
                    @if ($supplierMode === 'existing')
                        <select wire:model.live="supplierId" class="mt-2 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Select Supplier —</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="mt-2 flex items-center gap-2">
                            <input type="text" wire:model="newSupplierName" placeholder="New supplier name"
                                   class="flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <button type="button" wire:click="createSupplier"
                                    class="px-3 py-2 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition whitespace-nowrap">
                                Create &amp; link
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('newSupplierName')" class="mt-1" />
                    @endif
                </div>

                <div>
                    <x-input-label for="pw_date" value="Price effective date" />
                    <input id="pw_date" type="date" wire:model="effectiveDate"
                           class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
            </div>
        </div>

        {{-- Summary + legend --}}
        @php
            $priceChangeCount = collect($items)->whereNotNull('price_change')->count();
        @endphp
        <div class="mb-4 px-4 py-3 bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <span class="text-gray-500">Items: <strong>{{ $totalItems }}</strong></span>
                    <span class="text-green-600">Matched: <strong>{{ $matchedCount }}</strong></span>
                    <span class="text-indigo-600">New: <strong>{{ $newCount }}</strong></span>
                    @if ($priceChangeCount > 0)
                        <span class="text-amber-600">Price changes: <strong>{{ $priceChangeCount }}</strong></span>
                    @endif
                </div>
                @can('reports.view')
                    <a href="{{ route('reports.price-history') }}" target="_blank"
                       class="text-xs text-indigo-600 hover:text-indigo-800 underline">
                        Price history report →
                    </a>
                @endcan
            </div>
        </div>

        {{-- Items table --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-500 uppercase text-[10px] tracking-wider">
                    <tr>
                        <th class="px-4 py-2 text-left w-8">#</th>
                        <th class="px-4 py-2 text-left">Item (from document)</th>
                        <th class="px-4 py-2 text-left">Matched Ingredient</th>
                        <th class="px-4 py-2 text-left w-20">SKU</th>
                        <th class="px-4 py-2 text-right w-32">Price</th>
                        <th class="px-4 py-2 text-left w-24">UOM</th>
                        <th class="px-4 py-2 text-center w-28">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($items as $idx => $item)
                        @php
                            $bg = match($item['action']) {
                                'link'   => 'bg-green-50/50',
                                'create' => 'bg-indigo-50/50',
                                default  => 'bg-gray-50/30',
                            };
                        @endphp
                        <tr class="{{ $bg }}" wire:key="pw-item-{{ $idx }}">
                            <td class="px-4 py-2.5 text-gray-400">{{ $idx + 1 }}</td>
                            <td class="px-4 py-2.5">
                                <div class="font-medium text-gray-800">{{ $item['name'] }}</div>
                                @if ($item['category'])
                                    <span class="text-[10px] text-gray-400">{{ $item['category'] }}</span>
                                @endif
                                @if ($item['pack_size'] > 0 && $item['recipe_uom_raw'])
                                    <span class="text-[10px] text-gray-400 ml-1">· {{ rtrim(rtrim(number_format($item['pack_size'], 4, '.', ''), '0'), '.') }} {{ $item['recipe_uom_raw'] }} / {{ $item['uom_raw'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if ($item['ingredient_id'])
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-green-700 text-xs truncate max-w-[160px]">{{ $item['matched_name'] }}</span>
                                        @if ($item['confidence'] < 100)
                                            <span class="text-[10px] text-amber-500">{{ $item['confidence'] }}%</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-red-500 text-[10px] font-semibold">NEW</span>
                                @endif
                                @include('livewire.ingredients.partials.pw-ingredient-picker', [
                                    'idx' => $idx,
                                    'currentName' => $item['matched_name'] ?? '',
                                ])
                            </td>
                            <td class="px-4 py-2.5 font-mono text-gray-500 text-[11px]">{{ $item['code'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">
                                <div class="{{ $item['price_change'] !== null ? 'font-semibold' : '' }}">
                                    {{ $item['price'] > 0 ? number_format($item['price'], 2) : '—' }}
                                </div>
                                @if ($item['old_price'] !== null)
                                    @if ($item['price_change'] !== null)
                                        <div class="mt-0.5 text-[10px] flex items-center justify-end gap-1 leading-tight
                                                    {{ $item['price_change'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                            <span>{{ $item['price_change'] > 0 ? '▲' : '▼' }}</span>
                                            <span>{{ $item['price_change_pct'] > 0 ? '+' : '' }}{{ $item['price_change_pct'] }}%</span>
                                        </div>
                                        <div class="text-[10px] text-gray-400 leading-tight">was {{ number_format($item['old_price'], 2) }}</div>
                                    @else
                                        <div class="mt-0.5 text-[10px] text-gray-400 leading-tight">no change</div>
                                    @endif
                                @elseif ($item['already_linked'])
                                    <div class="mt-0.5 text-[10px] text-gray-400 leading-tight">no prior price</div>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <select wire:change="fixUom({{ $idx }}, $event.target.value)"
                                        class="w-full text-xs rounded border-gray-200 py-1 px-2">
                                    <option value="">—</option>
                                    @foreach ($uoms as $uom)
                                        <option value="{{ $uom->id }}" @selected($item['uom_id'] == $uom->id)>{{ $uom->abbreviation }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <select wire:change="setAction({{ $idx }}, $event.target.value)"
                                        class="text-xs rounded border-gray-200 py-1 px-2 w-24">
                                    @if ($item['ingredient_id'])
                                        <option value="link" @selected($item['action'] === 'link')>Link</option>
                                    @endif
                                    <option value="create" @selected($item['action'] === 'create')>Create</option>
                                    <option value="skip" @selected($item['action'] === 'skip')>Skip</option>
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Action bar --}}
        <div class="flex items-center justify-between">
            <a href="{{ route('ingredients.review-documents') }}" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">← Back</a>
            <button wire:click="import" wire:loading.attr="disabled"
                    class="px-6 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="import">Import {{ $totalItems }} items</span>
                <span wire:loading wire:target="import">Importing…</span>
            </button>
        </div>
    @endif

    {{-- Shared searchable ingredient picker — registered once via alpine:init,
         teleported popup to escape any ancestor transform. --}}
    <script data-navigate-once>
        (function () {
            if (window.__pwIngredientPickerRegistered) return;
            window.__pwIngredientPickerRegistered = true;
            const register = () => {
                if (!window.Alpine || !window.Alpine.data) return;

                window.Alpine.data('pwSharedPicker', () => ({
                    open: false, idx: null,
                    query: '', results: [], highlightIdx: 0,
                    popupStyle: '', triggerEl: null,

                    init() {
                        this._t = null;
                        this.$watch('query', () => {
                            if (this._t) clearTimeout(this._t);
                            this._t = setTimeout(() => this.filter(), 120);
                        });
                    },

                    openFor(detail) {
                        if (!detail) return;
                        this.idx = detail.idx;
                        this.triggerEl = detail.triggerEl || null;
                        this.query = '';
                        this.filter();
                        this.position();
                        this.open = true;
                        this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
                    },
                    position() {
                        if (!this.triggerEl) { this.popupStyle = 'top:30%;left:50%;transform:translateX(-50%);'; return; }
                        const r = this.triggerEl.getBoundingClientRect();
                        const w = 320, h = 320, vw = window.innerWidth, vh = window.innerHeight;
                        let left = r.left;
                        if (left + w > vw - 8) left = Math.max(8, vw - w - 8);
                        let top = r.bottom + 4;
                        if (top + h > vh - 8 && r.top > h) top = r.top - h - 4;
                        this.popupStyle = `left:${left}px;top:${top}px;`;
                    },
                    filter() {
                        const q = (this.query || '').trim().toLowerCase();
                        const list = window.__pwIngredientsList || [];
                        this.results = q
                            ? list.filter(i => (i.name || '').toLowerCase().includes(q)).slice(0, 50)
                            : list.slice(0, 50);
                        this.highlightIdx = 0;
                    },
                    move(d) {
                        if (!this.results.length) return;
                        this.highlightIdx = (this.highlightIdx + d + this.results.length) % this.results.length;
                    },
                    pick(ing) {
                        if (!ing || this.idx === null) return;
                        this.open = false;
                        try { this.$wire.call('fixMatch', Number(this.idx), Number(ing.id)); }
                        catch (e) { console.error('pw fixMatch failed', e); }
                    },
                    outside(ev) {
                        if (this.triggerEl && this.triggerEl.contains(ev.target)) return;
                        this.open = false;
                    },
                }));
            };
            if (window.Alpine && window.Alpine.version) register();
            else document.addEventListener('alpine:init', register, { once: true });
        })();
    </script>

    {{-- One shared picker for the whole review table --}}
    <div x-data="pwSharedPicker()"
         @open-pw-picker.window="openFor($event.detail)">
        <template x-teleport="body">
            <div x-show="open" x-cloak
                 @click.outside="outside($event)"
                 @keydown.escape.window="open = false"
                 :style="popupStyle"
                 class="fixed z-[90] w-80 bg-white border border-gray-200 rounded-lg shadow-xl text-gray-800">
                <div class="p-2 border-b border-gray-100">
                    <input type="text" x-model="query" x-ref="input"
                           @keydown.arrow-down.prevent="move(1)"
                           @keydown.arrow-up.prevent="move(-1)"
                           @keydown.enter.prevent="results.length && pick(results[highlightIdx] || results[0])"
                           placeholder="Search ingredient…"
                           class="w-full text-xs border-gray-200 rounded px-2 py-1.5 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" />
                </div>
                <ul x-show="results.length > 0" class="max-h-64 overflow-y-auto py-1">
                    <template x-for="(ing, i) in results" :key="ing.id">
                        <li @click.stop="pick(ing)"
                            @mouseenter="highlightIdx = i"
                            :class="i === highlightIdx ? 'bg-indigo-100 text-indigo-900' : 'hover:bg-indigo-50'"
                            class="px-3 py-1.5 text-xs cursor-pointer"
                            x-text="ing.name"></li>
                    </template>
                </ul>
                <div x-show="results.length === 0" x-cloak class="px-3 py-3 text-[11px] text-gray-400 italic text-center">
                    No ingredient matches — leave as "Create" to add a new one.
                </div>
            </div>
        </template>
    </div>
</div>
