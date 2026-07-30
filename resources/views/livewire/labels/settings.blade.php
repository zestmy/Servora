<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-6">
        <p class="text-xs text-gray-400">Labels / Settings</p>
        <h2 class="text-lg font-semibold text-gray-700 mt-1">Label settings</h2>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 max-w-2xl">
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
                <p class="mt-1 text-xs text-amber-600">
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
        </div>

        <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end">
            <button wire:click="save"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Save settings
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 max-w-2xl mt-4">
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
                        class="px-3 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 whitespace-nowrap">
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
                        class="mt-1 text-xs text-red-500 hover:underline">Remove stored key</button>
            @endif

            @if ($connectionResult)
                <p class="mt-2 px-3 py-2 rounded-lg text-xs {{ str_starts_with($connectionResult, 'Connected') ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                    {{ $connectionResult }}
                </p>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 max-w-2xl mt-4">
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
