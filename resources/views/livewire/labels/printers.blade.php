<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">Labels / Printers</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Label printers</h2>
        </div>
        <button wire:click="openCreate" class="px-3 md:px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            <span class="sm:hidden">+ Add</span>
            <span class="hidden sm:inline">+ Add Printer</span>
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <p class="text-sm text-gray-600">
            One record per label printer. The label size recorded here is what labels are printed at, so it must
            match the roll actually loaded in the printer.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            Printing goes through the browser on the outlet PC the printer is attached to — see
            <a href="{{ route('labels.settings') }}" class="text-indigo-600 hover:underline">Label settings</a> for
            the one-time Chrome setup.
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-[720px] divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Printer</th>
                        <th class="px-4 py-3 text-left">Outlet</th>
                        <th class="px-4 py-3 text-center w-32">Label size</th>
                        <th class="px-4 py-3 text-left">Default template</th>
                        <th class="px-4 py-3 text-center w-24">Status</th>
                        <th class="px-4 py-3 text-center w-28">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($printers as $printer)
                        <tr class="hover:bg-gray-50 {{ ! $printer->is_active ? 'opacity-60' : '' }}" wire:key="printer-{{ $printer->id }}">
                            <td class="px-4 py-3 font-medium text-gray-700">{{ $printer->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $printer->outlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center text-gray-600">
                                {{ rtrim(rtrim($printer->width_mm, '0'), '.') }}×{{ rtrim(rtrim($printer->height_mm, '0'), '.') }}mm
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $printer->defaultTemplate?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $printer->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $printer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="openEdit({{ $printer->id }})" class="text-indigo-600 hover:text-indigo-800 text-xs">Edit</button>
                                <button wire:click="delete({{ $printer->id }})"
                                        wire:confirm="Remove this printer?"
                                        class="ml-2 text-red-500 hover:text-red-700 text-xs">Remove</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">
                                No printers yet. Add one for each outlet that prints labels.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

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
                            <button @click="open = false" class="text-gray-400 hover:text-gray-600 p-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <form wire:submit.prevent="save" class="p-5 space-y-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Name <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="name" class="mt-1 w-full text-sm rounded-lg border-gray-300"
                                       placeholder="e.g. Kitchen Brother QL-820" />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Outlet <span class="text-red-500">*</span></label>
                                <select wire:model="outlet_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    <option value="">— Select —</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('outlet_id')" class="mt-1" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-semibold text-gray-600">Width (mm) <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.5" wire:model="width_mm" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                    <x-input-error :messages="$errors->get('width_mm')" class="mt-1" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-600">Height (mm) <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.5" wire:model="height_mm" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                    <x-input-error :messages="$errors->get('height_mm')" class="mt-1" />
                                </div>
                            </div>
                            <p class="text-xs text-gray-400">
                                Must match the roll loaded in the printer and the size set in its Windows driver.
                            </p>
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
                                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                                <button type="submit" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
