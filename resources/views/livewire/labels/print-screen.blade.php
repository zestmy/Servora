<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- Collapsed by default: guidance should be one click away, not in the
         way of someone who prints the same tray every morning. --}}
    <x-labels.guide class="mb-4" />

    <x-page-header title="Print labels" eyebrow="Labels">
        @can('labels.print')
            <x-slot:actions>
                <a href="{{ route('labels.sets') }}" class="btn-secondary">Print sets</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    {{-- Who and where. Both end up on the label and in the audit trail. --}}
    <div class="card p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label mb-1 block" for="printer">Printer</label>
                <select id="printer" wire:model.live="printerId" class="input">
                    <option value="">— Select printer —</option>
                    @foreach ($printers as $printer)
                        <option value="{{ $printer->id }}">
                            {{ $printer->name }} — {{ $printer->outlet?->name }}
                            ({{ rtrim(rtrim($printer->width_mm, '0'), '.') }}×{{ rtrim(rtrim($printer->height_mm, '0'), '.') }}mm)
                        </option>
                    @endforeach
                </select>
                @if ($printers->isEmpty())
                    <p class="mt-1 text-xs font-medium text-warning-800">
                        No printers set up.
                        <a href="{{ route('labels.printers') }}" class="underline">Add one</a> before printing.
                    </p>
                @endif
            </div>
            <div>
                <label class="label label-req mb-1 block" for="prepared-by">Prepared by</label>
                <select id="prepared-by" wire:model.live="employeeId"
                        class="input {{ $employeeId ? '' : 'border-warning-400 bg-warning-50 focus:border-warning-500 focus:ring-warning-500/25' }}">
                    <option value="">— Select staff —</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </select>
                @unless ($employeeId)
                    <p class="mt-1 text-xs font-medium text-warning-800">Required — every label records who prepped it.</p>
                @endunless
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Find something to label --}}
        <div class="card p-4">
            <label class="label mb-1 block" for="label-search">Search</label>
            <input id="label-search" type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Type at least 2 letters…"
                   class="input py-2.5 text-base">

            <div class="mt-3 space-y-3 max-h-80 overflow-y-auto">
                @forelse ($results as $group => $found)
                    @if (count($found['items']))
                        <div>
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-600">
                                {{ $group }}
                                <span class="normal-case tracking-normal text-gray-500">{{ $found['total'] }}</span>
                            </p>
                            <div class="space-y-1">
                                @foreach ($found['items'] as $item)
                                    <button type="button"
                                            wire:click="addItem('{{ $item['type'] }}', {{ $item['id'] }})"
                                            class="w-full rounded-control border border-gray-200 px-3 py-2.5 text-left text-sm font-medium text-gray-900 transition-colors hover:border-brand-300 hover:bg-brand-50">
                                        {{ $item['name'] }}
                                    </button>
                                @endforeach
                            </div>
                            {{-- Said out loud. A silently capped list is why
                                 items looked like they were missing. --}}
                            @if ($found['truncated'])
                                <p class="mt-1 text-xs font-medium text-warning-800">
                                    Showing {{ count($found['items']) }} of {{ $found['total'] }} — type more to narrow it down.
                                </p>
                            @endif
                        </div>
                    @endif
                @empty
                    <p class="text-sm text-gray-600 py-4 text-center">
                        {{ strlen(trim($search)) >= 2 ? 'Nothing found.' : 'Search for an ingredient, recipe or prep item.' }}
                    </p>
                @endforelse
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100">
                <label class="label mb-1 block" for="custom-name">Or label something not in the system</label>
                <div class="flex gap-2">
                    <input id="custom-name" type="text" wire:model="customName" wire:keydown.enter="addCustom"
                           placeholder="e.g. Chef's special sauce"
                           class="input flex-1">
                    <button wire:click="addCustom" class="btn-secondary">Add</button>
                </div>
            </div>
        </div>

        {{-- The queue --}}
        <div class="card p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-900">To print ({{ count($tray) }})</h2>
                @if (count($tray))
                    <button wire:click="clearTray" class="text-xs font-medium text-gray-600 hover:text-danger-700">Clear all</button>
                @endif
            </div>

            @error('tray')
                <p class="alert-danger mb-3 text-xs">{{ $message }}</p>
            @enderror

            <div class="space-y-2 max-h-[26rem] overflow-y-auto">
                @forelse ($tray as $index => $line)
                    <div class="rounded-control border border-gray-200 p-3" wire:key="tray-{{ $index }}-{{ $line['name'] }}">
                        <div class="flex items-start justify-between gap-2">
                            <p class="flex-1 text-sm font-medium text-gray-900">{{ $line['name'] }}</p>
                            <button wire:click="removeLine({{ $index }})"
                                    aria-label="Remove {{ $line['name'] }}"
                                    class="icon-btn icon-btn-danger -mr-1 -mt-1 h-7 w-7 shrink-0">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="mt-2 grid grid-cols-3 gap-2">
                            <div>
                                <label class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wider text-gray-600">Label</label>
                                <select wire:model.live="tray.{{ $index }}.label_type" class="input py-1.5 text-xs">
                                    @foreach ($labelTypes as $type => $caption)
                                        <option value="{{ $type }}">{{ $caption ?: 'Custom' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wider text-gray-600">Storage</label>
                                <select wire:model.live="tray.{{ $index }}.storage_state" class="input py-1.5 text-xs">
                                    @foreach ($states as $state => $stateLabel)
                                        <option value="{{ $state }}">{{ $stateLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wider text-gray-600">Copies</label>
                                <input type="number" min="1" max="99" wire:model="tray.{{ $index }}.copies"
                                       class="input py-1.5 text-xs">
                            </div>
                        </div>

                        {{-- The date that will actually be printed --}}
                        <div class="mt-2">
                            @if ($previews[$index]['manual'] ?? true)
                                <div class="flex items-center gap-2">
                                    <span class="whitespace-nowrap text-[10px] font-semibold uppercase tracking-wider text-warning-800">No rule — set use-by</span>
                                    <input type="datetime-local" wire:model.live="tray.{{ $index }}.end_at"
                                           class="input input-error flex-1 py-1.5 text-xs">
                                </div>
                            @else
                                <p class="text-xs text-gray-600">
                                    Use by
                                    <span class="font-semibold tabular-nums text-gray-900">
                                        {{ $previews[$index]['end_at']->format('d/m/Y H:i') }}
                                    </span>
                                    @if (($previews[$index]['source'] ?? null) === 'category')
                                        <span class="text-gray-600">(from category)</span>
                                    @elseif (in_array($previews[$index]['source'] ?? null, ['legacy', 'legacy_prep_recipe'], true))
                                        <span class="text-gray-600">(from item shelf life)</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm text-gray-600">Nothing queued yet.</p>
                @endforelse
            </div>

            <button wire:click="printTray" wire:loading.attr="disabled"
                    @disabled(! count($tray))
                    class="btn-primary mt-4 w-full py-3">
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
