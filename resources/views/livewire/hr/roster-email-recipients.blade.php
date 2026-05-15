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

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">HR / Duty Roster / Settings</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Email Recipients</h2>
            <p class="text-xs text-gray-500 mt-1">Configure additional email recipients (HR, Area Manager, etc.) who receive duty roster notifications.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('hr.duty-roster') }}"
               class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Back to Roster
            </a>
            @if ($outletId)
                <button wire:click="openCreate"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    + Add Recipient
                </button>
            @endif
        </div>
    </div>

    {{-- Outlet Selector --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700">Outlet:</label>
            <select wire:model.live="outletId" class="text-sm rounded-lg border-gray-300 shadow-sm min-w-[200px]">
                <option value="">Select an outlet...</option>
                @foreach ($outlets as $outlet)
                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($outletId)
        {{-- Recipients List --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center w-32">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($recipients as $recipient)
                        <tr class="{{ !$recipient->is_active ? 'bg-gray-50 text-gray-400' : '' }}">
                            <td class="px-4 py-3 font-medium">{{ $recipient->email }}</td>
                            <td class="px-4 py-3">{{ $recipient->name ?? '-' }}</td>
                            <td class="px-4 py-3">
                                @if ($recipient->role_label)
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full">
                                        {{ $recipient->role_label }}
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleActive({{ $recipient->id }})"
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full
                                               {{ $recipient->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $recipient->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button wire:click="openEdit({{ $recipient->id }})"
                                            class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                        Edit
                                    </button>
                                    <button wire:click="delete({{ $recipient->id }})"
                                            wire:confirm="Delete this recipient?"
                                            class="text-red-600 hover:text-red-800 text-xs font-medium">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                No email recipients configured for this outlet yet.
                                <button wire:click="openCreate" class="text-indigo-600 hover:underline ml-1">Add one now</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center text-gray-500">
            Please select an outlet to manage email recipients.
        </div>
    @endif

    {{-- Add/Edit Modal (teleported to body to escape sidebar transform) --}}
    <div x-data="{ open: @entangle('showForm') }">
    <template x-teleport="body">
    <div x-show="open" x-cloak
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-[100] overflow-y-auto">
        <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
        <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">{{ $editingId ? 'Edit Recipient' : 'Add Recipient' }}</h3>
                    <button @click="open = false" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form wire:submit.prevent="save" class="p-5 space-y-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" wire:model="f_email"
                               class="mt-1 w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. hr@company.com" />
                        @error('f_email') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Name</label>
                        <input type="text" wire:model="f_name"
                               class="mt-1 w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. John Smith" />
                        @error('f_name') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600">Role Label</label>
                        <input type="text" wire:model="f_role_label"
                               class="mt-1 w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. HR Manager, Area Manager" />
                        @error('f_role_label') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="f_is_active"
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                            {{ $editingId ? 'Update' : 'Add' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </template>
    </div>
</div>
