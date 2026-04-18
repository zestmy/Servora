<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">Settings / Sections</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Sections</h2>
        </div>
        <button wire:click="openCreate" class="px-3 md:px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            <span class="sm:hidden">+ Add</span>
            <span class="hidden sm:inline">+ Add Section</span>
        </button>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <p class="text-sm text-gray-600">
            Sections group employees by where they work — FOH (Front of House), BOH (Back of House), and any other
            divisions you define. Used for Overtime Claim filtering and the upcoming Duty Roster module.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            This is separate from <a href="{{ route('settings.departments') }}" class="text-indigo-600 hover:underline">Departments</a>,
            which are PO receiver / cost-tracking units.
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-[640px] divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-center w-24">Sort Order</th>
                    <th class="px-4 py-3 text-center w-28">Employees</th>
                    <th class="px-4 py-3 text-center w-28">Status</th>
                    <th class="px-4 py-3 text-center w-32">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($sections as $section)
                    <tr class="hover:bg-gray-50 {{ ! $section->is_active ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $section->name }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $section->sort_order }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $usage[$section->id] ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $section->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $section->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <button wire:click="openEdit({{ $section->id }})" title="Edit" class="text-indigo-500 hover:text-indigo-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="toggleActive({{ $section->id }})" title="{{ $section->is_active ? 'Deactivate' : 'Activate' }}" class="text-amber-500 hover:text-amber-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button wire:click="delete({{ $section->id }})" wire:confirm="Delete this section?" title="Delete" class="text-red-400 hover:text-red-600 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No sections yet.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    {{-- Modal (teleported to body) --}}
    <div x-data="{ open: @entangle('showModal') }">
    <template x-teleport="body">
        <div x-show="open" x-cloak
             @keydown.escape.window="open = false"
             class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
            <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $editingId ? 'Edit Section' : 'Add Section' }}</h3>
                        <button @click="open = false" class="text-gray-400 hover:text-gray-600 p-1">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form wire:submit.prevent="save" class="p-5 space-y-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Name <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="name" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. FOH, BOH, Management" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Sort Order</label>
                            <input type="number" wire:model="sort_order" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
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
