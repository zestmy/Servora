<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Collapsed by default: guidance should be one click away, not in the
         way of someone who prints the same tray every morning. --}}
    <x-labels.guide class="mb-4" />

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">Labels</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Print labels</h2>
        </div>
        @can('labels.print')
            <a href="{{ route('labels.sets') }}" class="px-3 py-2 text-sm text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50">
                Print sets
            </a>
        @endcan
    </div>

    {{-- Who and where. Both end up on the label and in the audit trail. --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Printer</label>
                <select wire:model.live="printerId" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">— Select printer —</option>
                    @foreach ($printers as $printer)
                        <option value="{{ $printer->id }}">
                            {{ $printer->name }} — {{ $printer->outlet?->name }}
                            ({{ rtrim(rtrim($printer->width_mm, '0'), '.') }}×{{ rtrim(rtrim($printer->height_mm, '0'), '.') }}mm)
                        </option>
                    @endforeach
                </select>
                @if ($printers->isEmpty())
                    <p class="mt-1 text-xs text-amber-600">
                        No printers set up.
                        <a href="{{ route('labels.printers') }}" class="underline">Add one</a> before printing.
                    </p>
                @endif
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">
                    Prepared by <span class="text-red-500">*</span>
                </label>
                <select wire:model.live="employeeId"
                        class="w-full rounded-lg text-sm {{ $employeeId ? 'border-gray-300' : 'border-amber-300 bg-amber-50' }}">
                    <option value="">— Select staff —</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </select>
                @unless ($employeeId)
                    <p class="mt-1 text-xs text-amber-600">Required — every label records who prepped it.</p>
                @endunless
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Find something to label --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Type at least 2 letters…"
                   class="w-full rounded-lg border-gray-300 text-base py-2.5">

            <div class="mt-3 space-y-3 max-h-80 overflow-y-auto">
                @forelse ($results as $group => $found)
                    @if (count($found['items']))
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-400 mb-1">
                                {{ $group }}
                                <span class="normal-case tracking-normal text-gray-300">{{ $found['total'] }}</span>
                            </p>
                            <div class="space-y-1">
                                @foreach ($found['items'] as $item)
                                    <button type="button"
                                            wire:click="addItem('{{ $item['type'] }}', {{ $item['id'] }})"
                                            class="w-full text-left px-3 py-2.5 rounded-lg border border-gray-100 hover:bg-indigo-50 hover:border-indigo-200 text-sm text-gray-700">
                                        {{ $item['name'] }}
                                    </button>
                                @endforeach
                            </div>
                            {{-- Said out loud. A silently capped list is why
                                 items looked like they were missing. --}}
                            @if ($found['truncated'])
                                <p class="mt-1 text-xs text-amber-600">
                                    Showing {{ count($found['items']) }} of {{ $found['total'] }} — type more to narrow it down.
                                </p>
                            @endif
                        </div>
                    @endif
                @empty
                    <p class="text-sm text-gray-400 py-4 text-center">
                        {{ strlen(trim($search)) >= 2 ? 'Nothing found.' : 'Search for an ingredient, recipe or prep item.' }}
                    </p>
                @endforelse
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100">
                <label class="block text-xs font-medium text-gray-500 mb-1">Or label something not in the system</label>
                <div class="flex gap-2">
                    <input type="text" wire:model="customName" wire:keydown.enter="addCustom"
                           placeholder="e.g. Chef's special sauce"
                           class="flex-1 rounded-lg border-gray-300 text-sm">
                    <button wire:click="addCustom"
                            class="px-3 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Add</button>
                </div>
            </div>
        </div>

        {{-- The queue --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">
                    To print ({{ count($tray) }})
                </h3>
                @if (count($tray))
                    <button wire:click="clearTray" class="text-xs text-gray-400 hover:text-red-500">Clear all</button>
                @endif
            </div>

            @error('tray')
                <p class="mb-3 px-3 py-2 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg">{{ $message }}</p>
            @enderror

            <div class="space-y-2 max-h-[26rem] overflow-y-auto">
                @forelse ($tray as $index => $line)
                    <div class="border border-gray-100 rounded-lg p-3" wire:key="tray-{{ $index }}-{{ $line['name'] }}">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium text-gray-700 flex-1">{{ $line['name'] }}</p>
                            <button wire:click="removeLine({{ $index }})"
                                    class="text-gray-300 hover:text-red-500 text-sm leading-none">×</button>
                        </div>

                        <div class="mt-2 grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-[10px] uppercase text-gray-400 mb-0.5">Label</label>
                                <select wire:model.live="tray.{{ $index }}.label_type" class="w-full rounded border-gray-200 text-xs py-1">
                                    @foreach ($labelTypes as $type => $caption)
                                        <option value="{{ $type }}">{{ $caption ?: 'Custom' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase text-gray-400 mb-0.5">Storage</label>
                                <select wire:model.live="tray.{{ $index }}.storage_state" class="w-full rounded border-gray-200 text-xs py-1">
                                    @foreach ($states as $state => $stateLabel)
                                        <option value="{{ $state }}">{{ $stateLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase text-gray-400 mb-0.5">Copies</label>
                                <input type="number" min="1" max="99" wire:model="tray.{{ $index }}.copies"
                                       class="w-full rounded border-gray-200 text-xs py-1">
                            </div>
                        </div>

                        {{-- The date that will actually be printed --}}
                        <div class="mt-2">
                            @if ($previews[$index]['manual'] ?? true)
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] uppercase text-amber-600 whitespace-nowrap">No rule — set use-by</span>
                                    <input type="datetime-local" wire:model.live="tray.{{ $index }}.end_at"
                                           class="flex-1 rounded border-amber-200 text-xs py-1">
                                </div>
                            @else
                                <p class="text-xs text-gray-500">
                                    Use by
                                    <span class="font-semibold text-gray-700">
                                        {{ $previews[$index]['end_at']->format('d/m/Y H:i') }}
                                    </span>
                                    @if (($previews[$index]['source'] ?? null) === 'category')
                                        <span class="text-gray-400">(from category)</span>
                                    @elseif (in_array($previews[$index]['source'] ?? null, ['legacy', 'legacy_prep_recipe'], true))
                                        <span class="text-gray-400">(from item shelf life)</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 py-10 text-center">Nothing queued yet.</p>
                @endforelse
            </div>

            <button wire:click="printTray" wire:loading.attr="disabled"
                    @disabled(! count($tray))
                    class="mt-4 w-full py-3 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                <span wire:loading.remove wire:target="printTray">Print {{ count($tray) ? count($tray) . ' item' . (count($tray) === 1 ? '' : 's') : '' }}</span>
                <span wire:loading wire:target="printTray">Printing…</span>
            </button>
        </div>
    </div>

    {{--
        The print target. The whole batch arrives as one document and goes out
        as one print() call — separate jobs would race under kiosk printing and
        come off the roll out of order.
    --}}
    <iframe id="label-print-frame" class="hidden" aria-hidden="true" tabindex="-1"></iframe>

    @script
    <script>
        window.addEventListener('label-print', (event) => {
            const html  = event.detail.document;
            const frame = document.getElementById('label-print-frame');

            if (! html || ! frame) {
                return;
            }

            // onload before srcdoc: setting srcdoc can resolve immediately for
            // small documents and the handler would otherwise never fire.
            frame.onload = () => {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            };

            frame.srcdoc = html;
        });
    </script>
    @endscript
</div>
