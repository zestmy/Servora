<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('labels.sets') }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="page-eyebrow">Labels / Print sets</p>
                <h1 class="page-title mt-1">{{ $set->name }}</h1>
            </div>
        </div>
    </div>

    <div class="card p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Printer</label>
                <select wire:model.live="printerId" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">— Select printer —</option>
                    @foreach ($printers as $printer)
                        <option value="{{ $printer->id }}">{{ $printer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">
                    Prepared by <span class="text-danger-500">*</span>
                </label>
                <select wire:model.live="employeeId"
                        class="w-full rounded-lg text-sm {{ $employeeId ? 'border-gray-300' : 'border-warning-300 bg-warning-50' }}">
                    <option value="">— Select staff —</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </select>
                @unless ($employeeId)
                    <p class="mt-1 text-xs text-warning-600">Required — every label records who prepped it.</p>
                @endunless
            </div>
        </div>
    </div>

    @error('print')
        <p class="mb-3 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">{{ $message }}</p>
    @enderror

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
            <p class="text-xs text-gray-500">Untick anything you haven't prepped today.</p>
            <div class="flex gap-2 text-xs">
                <button wire:click="selectAll(true)" class="text-brand-600 hover:underline">Select all</button>
                <span class="text-gray-500">|</span>
                <button wire:click="selectAll(false)" class="text-gray-500 hover:underline">None</button>
            </div>
        </div>

        <div class="divide-y divide-gray-50">
            @forelse ($lines as $line)
                <label class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer"
                       wire:key="line-{{ $line->id }}">
                    <input type="checkbox" wire:model.live="selected.{{ $line->id }}"
                           class="mt-1 rounded border-gray-300 text-brand-600 h-5 w-5">

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-700">{{ $line->displayName() }}</p>
                        <p class="text-xs text-gray-600 mt-0.5">
                            {{ \App\Models\LabelTemplate::LABEL_TYPES[$line->label_type] ?? $line->label_type }}
                            · {{ \App\Models\ShelfLifeRule::stateLabel($line->storage_state) }}
                        </p>

                        @if ($previews[$line->id]['manual'] ?? true)
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="text-[10px] uppercase text-warning-600 whitespace-nowrap">No rule — set use-by</span>
                                <input type="datetime-local" wire:model.live="endAt.{{ $line->id }}"
                                       class="rounded border-warning-200 text-xs py-1">
                            </div>
                        @else
                            <p class="text-xs text-gray-500 mt-0.5">
                                Use by <span class="font-semibold text-gray-700">{{ $previews[$line->id]['end_at']->format('d/m/Y H:i') }}</span>
                            </p>
                        @endif
                    </div>

                    <div class="w-16">
                        <label class="block text-[10px] uppercase text-gray-600 mb-0.5">Copies</label>
                        <input type="number" min="1" max="99" wire:model="copies.{{ $line->id }}"
                               class="w-full rounded border-gray-200 text-xs py-1">
                    </div>
                </label>
            @empty
                <p class="px-4 py-10 text-center text-gray-600 text-sm">
                    This set has no items yet.
                    <a href="{{ route('labels.sets') }}" class="text-brand-600 hover:underline">Add some</a>.
                </p>
            @endforelse
        </div>
    </div>

    @if ($lines->count())
        <button wire:click="print" wire:loading.attr="disabled"
                class="btn-primary mt-4 w-full py-3">
            <span wire:loading.remove wire:target="print">Print selected</span>
            <span wire:loading wire:target="print">Printing…</span>
        </button>
    @endif

    <iframe id="label-print-frame" class="hidden" aria-hidden="true" tabindex="-1"></iframe>

    @script
    <script>
        window.addEventListener('label-print', (event) => {
            const html  = event.detail.document;
            const frame = document.getElementById('label-print-frame');

            if (! html || ! frame) {
                return;
            }

            frame.onload = () => {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            };

            frame.srcdoc = html;
        });
    </script>
    @endscript
</div>
