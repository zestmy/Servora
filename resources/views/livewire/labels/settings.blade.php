<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-6">
        <p class="page-eyebrow">Labels / Settings</p>
        <h1 class="page-title mt-1">Label settings</h1>
    </div>

    <div class="card p-5 max-w-2xl">
        <div class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Use-by rounding</label>
                <select wire:model="use_by_rounding" class="w-full rounded-lg border-gray-300 text-sm">
                    @foreach ($roundingOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    How a use-by date is rounded once the shelf life is added on. End of day is the usual choice —
                    prepped at 14:30 with a 3-day life gives 23:59 on day three.
                </p>
                <p class="mt-1 text-xs text-warning-600">
                    Shelf lives measured in minutes or hours are never rounded, whichever option you pick.
                    Rounding a 4-hour life up to 23:59 would extend it.
                </p>
                <x-input-error :messages="$errors->get('use_by_rounding')" class="mt-1" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Footer text</label>
                <input type="text" wire:model="footer_text" maxlength="120"
                       placeholder="e.g. Keep refrigerated below 4°C"
                       class="w-full rounded-lg border-gray-300 text-sm">
                <p class="mt-1 text-xs text-gray-500">
                    Optional. Only appears on labels whose template includes a footer field.
                </p>
                <x-input-error :messages="$errors->get('footer_text')" class="mt-1" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fallback template</label>
                <select wire:model="default_template_id" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">— Use each label type's own default —</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">
                            {{ $template->name }} ({{ $template->label_type }}, {{ rtrim(rtrim($template->width_mm, '0'), '.') }}×{{ rtrim(rtrim($template->height_mm, '0'), '.') }}mm)
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    Used only when a label type has no default of its own. Normally leave this alone.
                </p>
                <x-input-error :messages="$errors->get('default_template_id')" class="mt-1" />
            </div>

            {{-- Printed on the QR label stuck to each unit, so staff can
                 check the unit against it. --}}
            <div class="pt-4 border-t border-gray-100">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Storage temperatures</label>
                        <p class="mt-1 text-xs text-gray-500">
                            Printed on print-set QR labels. Leave a box empty to use the standard figure
                            shown in grey — that way a later correction to the standard still reaches you.
                        </p>
                    </div>
                    <button wire:click="resetTemperatures"
                            wire:confirm="Clear your own wording and go back to the standard figures?"
                            class="shrink-0 text-xs text-gray-600 hover:text-gray-900 whitespace-nowrap">
                        Reset all
                    </button>
                </div>

                <div class="mt-3 space-y-2">
                    @foreach ($states as $state => $stateLabel)
                        <div class="flex items-center gap-3">
                            <span class="w-24 shrink-0 text-sm text-gray-700">{{ $stateLabel }}</span>
                            <input type="text" wire:model="temperatures.{{ $state }}" maxlength="40"
                                   placeholder="{{ $defaults[$state] ?? 'no standard figure' }}"
                                   class="flex-1 rounded-lg border-gray-300 text-sm">
                        </div>
                        <x-input-error :messages="$errors->get('temperatures.' . $state)" class="mt-1" />
                    @endforeach
                </div>

                <p class="mt-2 text-xs text-gray-600">
                    Free text, so write it the way your auditor expects — “0-5°C”, “below -18°C”, and so on.
                </p>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end">
            <button wire:click="save"
                    class="btn-primary">
                Save settings
            </button>
        </div>
    </div>

    <div class="card p-5 max-w-2xl mt-4">
        <h3 class="text-sm font-semibold text-gray-700">PrintNode (optional)</h3>
        <p class="mt-2 text-xs text-gray-500">
            Only needed for printers that aren't attached to the PC doing the printing — a tablet in the kitchen,
            or head office printing to an outlet. Leave this empty to keep printing through the browser.
        </p>

        <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">API key</label>
            <div class="flex gap-2">
                <input type="password" wire:model="printnode_api_key" autocomplete="off"
                       placeholder="{{ $hasApiKey ? '•••••••• (a key is set — type to replace it)' : 'Paste your PrintNode API key' }}"
                       class="flex-1 rounded-lg border-gray-300 text-sm">
                <button wire:click="testConnection"
                        class="btn-secondary whitespace-nowrap">
                    Test
                </button>
            </div>
            <p class="mt-1 text-xs text-gray-500">
                Stored encrypted and never shown again. Leaving this empty keeps the existing key —
                it won't wipe it.
            </p>
            <x-input-error :messages="$errors->get('printnode_api_key')" class="mt-1" />

            @if ($hasApiKey)
                <button wire:click="clearApiKey" wire:confirm="Remove the stored PrintNode key?"
                        class="mt-1 text-xs text-danger-500 hover:underline">Remove stored key</button>
            @endif

            @if ($connectionResult)
                <p class="mt-2 px-3 py-2 rounded-lg text-xs {{ str_starts_with($connectionResult, 'Connected') ? 'bg-success-50 text-success-700 border border-success-200' : 'bg-warning-50 text-warning-700 border border-warning-200' }}">
                    {{ $connectionResult }}
                </p>
            @endif
        </div>
    </div>

    <div class="card p-5 max-w-2xl mt-4">
        <h3 class="text-sm font-semibold text-gray-700">Printing from the outlet PC</h3>
        <p class="mt-2 text-xs text-gray-500">
            Labels print through the browser straight to the label printer attached to the outlet PC.
            For printing without a dialog on every label, launch Chrome on that PC with kiosk printing:
        </p>
        <pre class="mt-2 p-3 bg-gray-50 rounded-lg text-xs text-gray-700 overflow-x-auto">chrome.exe --kiosk-printing --app={{ url('/labels') }}</pre>
        <p class="mt-2 text-xs text-gray-500">
            Set the label printer as the PC's default printer, and set the label size in the printer driver's
            preferences so it matches the template. A mismatch scales and clips silently.
        </p>
    </div>
</div>
