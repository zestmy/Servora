<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a data-back href="{{ route('settings.index') }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h2 class="page-title">Tax Rates</h2>
        </div>
        <button wire:click="openCreate" class="btn-primary">
            + New Tax Rate
        </button>
    </div>

    <div class="card overflow-hidden">
        @if ($taxRates->count() > 0)
            <div class="overflow-x-auto"><table class="table-surface min-w-[900px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Country</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-right">Rate</th>
                        <th class="px-4 py-3 text-center">Type</th>
                        <th class="px-4 py-3 text-center">Default</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($taxRates as $tr)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-700">{{ $tr->country_code }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $tr->name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">{{ $tr->rate }}%</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded text-xs {{ $tr->is_inclusive ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $tr->is_inclusive ? 'Inclusive' : 'Exclusive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($tr->is_default)
                                    <span class="text-success-600 font-medium text-xs">Default</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded text-xs {{ $tr->is_active ? 'bg-success-50 text-success-600' : 'bg-danger-50 text-danger-600' }}">
                                    {{ $tr->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="openEdit({{ $tr->id }})" class="text-sm text-brand-600 hover:text-brand-800">Edit</button>
                                <button wire:click="delete({{ $tr->id }})" data-confirm-delete="Delete this tax rate?" class="text-sm text-danger-500 hover:text-danger-700 ml-2">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @else
            <div class="p-8 text-center text-gray-600 text-sm">No tax rates configured.</div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @teleport('body')
    <div x-data="{}" x-show="$wire.showForm" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-900/50" @click="$wire.set('showForm', false)"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10 p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">{{ $editId ? 'Edit' : 'New' }} Tax Rate</h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Country Code *</label>
                            <input type="text" wire:model="country_code" maxlength="2" class="w-full rounded-lg border-gray-300 text-sm uppercase" placeholder="MY" />
                            @error('country_code') <p class="text-xs text-danger-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Name *</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 text-sm" placeholder="SST" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Rate (%) *</label>
                        <input type="number" step="0.01" min="0" max="100" wire:model="rate" class="w-full rounded-lg border-gray-300 text-sm" placeholder="6.00" />
                    </div>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="is_inclusive" class="rounded border-gray-300 text-brand-600" />
                            <span class="text-sm text-gray-600">Tax inclusive</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="is_default" class="rounded border-gray-300 text-brand-600" />
                            <span class="text-sm text-gray-600">Default</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-brand-600" />
                            <span class="text-sm text-gray-600">Active</span>
                        </label>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button wire:click="$set('showForm', false)" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                    <button wire:click="save" class="btn-primary">
                        {{ $editId ? 'Update' : 'Create' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endteleport
</div>
