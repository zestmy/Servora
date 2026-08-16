<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="page-eyebrow">Labels / Printers</p>
            <h1 class="page-title mt-1">Label printers</h1>
        </div>
        <button wire:click="openCreate" class="btn-primary">
            <span class="sm:hidden">+ Add</span>
            <span class="hidden sm:inline">+ Add Printer</span>
        </button>
    </div>

    <div class="card p-5 mb-4">
        <p class="text-sm text-gray-600">
            One record per label printer. The label size recorded here is what labels are printed at, so it must
            match the roll actually loaded in the printer.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            Printing goes through the browser on the outlet PC the printer is attached to — see
            <a href="{{ route('labels.settings') }}" class="text-brand-600 hover:underline">Label settings</a> for
            the one-time Chrome setup.
        </p>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[720px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Printer</th>
                        <th class="px-4 py-3 text-left">Outlet</th>
                        <th class="px-4 py-3 text-center w-32">Label size</th>
                        <th class="px-4 py-3 text-left">Default template</th>
                        <th class="px-4 py-3 text-center w-24">Status</th>
                        <th class="px-4 py-3 text-center w-28">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($printers as $printer)
                        <tr class="hover:bg-gray-50 {{ ! $printer->is_active ? 'opacity-60' : '' }}" wire:key="printer-{{ $printer->id }}">
                            <td class="px-4 py-3 font-medium text-gray-700">{{ $printer->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $printer->outlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">
                                {{ rtrim(rtrim($printer->width_mm, '0'), '.') }}×{{ rtrim(rtrim($printer->height_mm, '0'), '.') }}mm
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $printer->defaultTemplate?->name ?? '—' }}
                                @if ($printer->driver === 'printnode')
                                    <span class="block text-xs text-brand-600">
                                        via PrintNode #{{ $printer->printnode_printer_id }}
                                        @if ($printer->printnode_paper) · {{ $printer->printnode_paper }} @endif
                                    </span>
                                @elseif ($printer->driver === 'agent')
                                    <span class="block text-xs text-brand-600">
                                        via {{ $printer->printAgent?->name ?? 'agent (removed)' }} · {{ $printer->agent_printer_name }}
                                        @if ($printer->agent_paper) · {{ $printer->agent_paper }} @endif
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                {{-- Two different questions, stacked. Active is
                                     whether it is configured for use at all;
                                     the line under it is whether we can
                                     currently reach it. explainLocal is off —
                                     "prints through this device" is copy for
                                     the chef at the printer, not for an admin
                                     reading a list. --}}
                                <span class="{{ $printer->is_active ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $printer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <x-labels.printer-status :printer="$printer"
                                                         :explain-local="false"
                                                         class="mt-1 block w-fit mx-auto" />
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button wire:click="printCalibration({{ $printer->id }})"
                                        class="text-gray-500 hover:text-gray-700 text-xs"
                                        title="Print a ruler to measure what this printer clips">Calibrate</button>
                                <button wire:click="openEdit({{ $printer->id }})" class="ml-2 text-brand-600 hover:text-brand-800 text-xs">Edit</button>
                                <button wire:click="delete({{ $printer->id }})"
                                        data-confirm-delete="Remove this printer?"
                                        class="ml-2 text-danger-500 hover:text-danger-700 text-xs">Remove</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-600 text-sm">
                                No printers yet. Add one for each outlet that prints labels.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Calibration prints through the same path a real label uses, so it
         exercises the driver exactly as the chef's prints will. --}}
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

    <div x-data="{ open: @entangle('showModal') }">
        <template x-teleport="body">
            <div x-show="open" x-cloak
                 @keydown.escape.window="open = false"
                 class="fixed inset-0 z-[100] overflow-y-auto">
                <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
                <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
                        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">{{ $editingId ? 'Edit Printer' : 'Add Printer' }}</h3>
                            <button @click="open = false" class="text-gray-600 hover:text-gray-900 p-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <form wire:submit.prevent="save" class="p-5 space-y-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Name <span class="text-danger-500">*</span></label>
                                <input type="text" wire:model="name" class="mt-1 w-full text-sm rounded-lg border-gray-300"
                                       placeholder="e.g. Kitchen Brother QL-820" />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Outlet <span class="text-danger-500">*</span></label>
                                <select wire:model="outlet_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    <option value="">— Select —</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('outlet_id')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">How it prints</label>
                                <select wire:model.live="driver" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    @foreach (\App\Models\LabelPrinter::DRIVERS as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-600">
                                    Browser prints from whichever PC the chef is using. Servora agent prints from
                                    the server through a
                                    <a href="{{ route('labels.agents') }}" class="text-brand-600 hover:underline">paired agent</a>
                                    on the outlet PC — no subscription. PrintNode does the same through
                                    PrintNode's paid service.
                                </p>
                            </div>

                            @if ($driver === 'agent')
                                <div class="p-3 bg-gray-50 rounded-lg space-y-2">
                                    <label class="text-xs font-semibold text-gray-600">Print agent <span class="text-danger-500">*</span></label>

                                    @if (! $outlet_id)
                                        <p class="px-3 py-2 bg-warning-50 border border-warning-200 text-warning-700 text-xs rounded-lg">
                                            Pick the outlet first — agents belong to one outlet.
                                        </p>
                                    @elseif (! $this->agentOptions)
                                        <p class="px-3 py-2 bg-warning-50 border border-warning-200 text-warning-700 text-xs rounded-lg">
                                            No agent is paired at this outlet yet. Set one up under
                                            <a href="{{ route('labels.agents') }}" class="underline">Print agents</a>.
                                        </p>
                                    @endif

                                    <select wire:model.live="print_agent_id" class="w-full text-sm rounded-lg border-gray-300">
                                        <option value="">— Select —</option>
                                        @foreach ($this->agentOptions as $agent)
                                            <option value="{{ $agent->id }}">
                                                {{ $agent->name }} ({{ $agent->statusLabel() }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('print_agent_id')" class="mt-1" />

                                    <div class="pt-1">
                                        <label class="text-xs font-semibold text-gray-600">Printer on that PC <span class="text-danger-500">*</span></label>
                                        <select wire:model.live="agent_printer_name" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                            <option value="">— Select —</option>
                                            @foreach ($this->agentPrinterOptions as $reported)
                                                <option value="{{ $reported['name'] }}">
                                                    {{ $reported['name'] }}@if ($reported['is_default']) — Windows default @endif
                                                </option>
                                            @endforeach
                                            {{-- Keep a saved selection visible even when the agent hasn't
                                                 reported yet, so opening the form can't silently blank it —
                                                 the same guard as the PrintNode picker below. --}}
                                            @if ($agent_printer_name && ! collect($this->agentPrinterOptions)->contains('name', $agent_printer_name))
                                                <option value="{{ $agent_printer_name }}">{{ $agent_printer_name }} (not in current list)</option>
                                            @endif
                                        </select>
                                        <x-input-error :messages="$errors->get('agent_printer_name')" class="mt-1" />
                                    </div>

                                    <div class="pt-1">
                                        <label class="text-xs font-semibold text-gray-600">Paper / form</label>
                                        <select wire:model="agent_paper" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                            <option value="">Driver default</option>
                                            @foreach ($this->agentPaperOptions as $paper)
                                                <option value="{{ $paper['name'] }}">
                                                    {{ $paper['name'] }}@if ($paper['size']) — {{ $paper['size'] }} @endif
                                                </option>
                                            @endforeach
                                            @if ($agent_paper && ! collect($this->agentPaperOptions)->contains('name', $agent_paper))
                                                <option value="{{ $agent_paper }}">{{ $agent_paper }} (not in current list)</option>
                                            @endif
                                        </select>
                                        <p class="mt-1 text-xs text-gray-600">
                                            Leave on driver default only if that default is already your label
                                            stock. If it isn't, the driver rotates or shrinks the label to fit
                                            its own paper — pick the {{ $width_mm }} × {{ $height_mm }} mm form here instead.
                                        </p>
                                    </div>
                                </div>
                            @endif

                            @if ($driver === 'printnode')
                                <div class="p-3 bg-gray-50 rounded-lg space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="text-xs font-semibold text-gray-600">PrintNode printer <span class="text-danger-500">*</span></label>
                                        <button type="button" wire:click="loadRemotePrinters"
                                                class="text-xs text-brand-600 hover:underline">Refresh list</button>
                                    </div>

                                    @if ($remoteError)
                                        <p class="px-3 py-2 bg-warning-50 border border-warning-200 text-warning-700 text-xs rounded-lg">
                                            {{ $remoteError }}
                                        </p>
                                    @endif

                                    <select wire:model.live="printnode_printer_id" class="w-full text-sm rounded-lg border-gray-300">
                                        <option value="">— Select —</option>
                                        @foreach ($remotePrinters as $remote)
                                            <option value="{{ $remote['id'] }}">
                                                {{ $remote['name'] }}
                                                @if ($remote['computer']) — {{ $remote['computer'] }} @endif
                                                ({{ $remote['state'] }})
                                            </option>
                                        @endforeach
                                        {{-- Keep a saved selection visible even if the list didn't load,
                                             so opening the form offline can't silently blank it. --}}
                                        @if ($printnode_printer_id && ! collect($remotePrinters)->contains('id', (int) $printnode_printer_id))
                                            <option value="{{ $printnode_printer_id }}">Printer #{{ $printnode_printer_id }} (not in current list)</option>
                                        @endif
                                    </select>
                                    <x-input-error :messages="$errors->get('printnode_printer_id')" class="mt-1" />

                                    <div class="pt-1">
                                        <label class="text-xs font-semibold text-gray-600">Paper / form</label>
                                        <select wire:model="printnode_paper" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                            <option value="">Driver default</option>
                                            @foreach ($this->paperOptions as $paper)
                                                <option value="{{ $paper['name'] }}">
                                                    {{ $paper['name'] }}@if ($paper['size']) — {{ $paper['size'] }} @endif
                                                </option>
                                            @endforeach
                                            {{-- Same reasoning as above: don't blank a saved paper just
                                                 because the printer is offline right now. --}}
                                            @if ($printnode_paper && ! collect($this->paperOptions)->contains('name', $printnode_paper))
                                                <option value="{{ $printnode_paper }}">{{ $printnode_paper }} (not in current list)</option>
                                            @endif
                                        </select>
                                        <p class="mt-1 text-xs text-gray-600">
                                            Leave on driver default only if that default is already your label
                                            stock. If it isn't, the driver rotates or shrinks the label to fit
                                            its own paper — pick the {{ $width_mm }} × {{ $height_mm }} mm form here instead.
                                        </p>
                                    </div>
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-semibold text-gray-600">Width (mm) <span class="text-danger-500">*</span></label>
                                    <input type="number" step="0.5" wire:model="width_mm" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                    <x-input-error :messages="$errors->get('width_mm')" class="mt-1" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-600">Height (mm) <span class="text-danger-500">*</span></label>
                                    <input type="number" step="0.5" wire:model="height_mm" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                    <x-input-error :messages="$errors->get('height_mm')" class="mt-1" />
                                </div>
                            </div>
                            <p class="text-xs text-gray-600">
                                Must match the roll loaded in the printer and the size set in its Windows driver.
                            </p>

                            <div class="pt-2 border-t border-gray-100">
                                <label class="inline-flex items-start gap-2">
                                    <input type="checkbox" wire:model="rotate_90" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                    <span>
                                        <span class="text-sm text-gray-700">Rotate 90°</span>
                                        <span class="block text-xs text-gray-600">
                                            Turn this on if the printer driver only offers the label the other way up
                                            — e.g. it lists 40 × 70 when your stock is 70 × 40. The page is sent at
                                            the swapped size and the label is turned to fit, instead of overflowing
                                            and eating several labels per print.
                                        </span>
                                    </span>
                                </label>
                            </div>

                            <div class="pt-2 border-t border-gray-100">
                                <p class="text-xs font-semibold text-gray-600">Print offset (mm)</p>
                                <p class="text-xs text-gray-600 mt-0.5">
                                    Leave at zero until you've printed a calibration label. If the printer clips
                                    3mm off the left, put 3 in X and the content shifts right to compensate.
                                </p>
                                <div class="grid grid-cols-2 gap-3 mt-2">
                                    <div>
                                        <label class="text-xs text-gray-500">X (right +)</label>
                                        <input type="number" step="0.5" wire:model="offset_x_mm" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                        <x-input-error :messages="$errors->get('offset_x_mm')" class="mt-1" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500">Y (down +)</label>
                                        <input type="number" step="0.5" wire:model="offset_y_mm" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                        <x-input-error :messages="$errors->get('offset_y_mm')" class="mt-1" />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Default template</label>
                                <select wire:model="default_template_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    <option value="">— Use each label type's default —</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->label_type }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('default_template_id')" class="mt-1" />
                            </div>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                <button type="button" @click="open = false" class="btn-secondary">Cancel</button>
                                <button type="submit" class="btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
