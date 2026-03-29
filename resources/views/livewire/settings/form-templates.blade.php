<div>
    {{-- Flash messages --}}
    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg flex justify-between">
            <span>{{ session('success') }}</span>
            <button @click="show = false" class="text-green-500 hover:text-green-700">✕</button>
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('purchasing.index') }}" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400"><a href="{{ route('purchasing.index') }}" class="hover:underline">Purchasing</a> / Order Templates</p>
            <h2 class="text-lg font-semibold text-gray-800 mt-0.5">Form Templates</h2>
        </div>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + New Template
        </button>
    </div>

    {{-- Info banner --}}
    <div class="mb-4 px-4 py-3 bg-blue-50 border border-blue-200 text-blue-700 text-sm rounded-lg">
        Templates let you pre-define item lists for <strong>Stock Takes</strong>, <strong>Purchase Orders</strong>, and <strong>Wastage</strong> entries.
        Each section (Bar, Kitchen, Pastry, etc.) can have its own template for faster data entry.
    </div>

    {{-- Type filter tabs --}}
    <div class="flex gap-2 mb-4">
        @foreach (['' => 'All', 'stock_take' => 'Stock Take', 'purchase_order' => 'Purchase Order', 'wastage' => 'Wastage'] as $val => $label)
            <button wire:click="$set('typeFilter', '{{ $val }}')"
                    class="px-3 py-1.5 text-sm font-medium rounded-lg transition
                        {{ $typeFilter === $val ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-indigo-300' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Templates table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($templates->isNotEmpty())
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left">Template Name</th>
                        <th class="px-4 py-3 text-left w-36">Type</th>
                        <th class="px-4 py-3 text-left">Description</th>
                        <th class="px-4 py-3 text-center w-16">Items</th>
                        <th class="px-4 py-3 text-center w-20">Status</th>
                        <th class="px-4 py-3 text-right w-32">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($templates as $t)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-800">
                                <a href="{{ route('settings.form-templates.edit', $t->id) }}"
                                   class="hover:text-indigo-600 transition">
                                    {{ $t->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $typeColors = [
                                        'stock_take'     => 'bg-teal-100 text-teal-700',
                                        'purchase_order' => 'bg-blue-100 text-blue-700',
                                        'wastage'        => 'bg-red-100 text-red-700',
                                    ];
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $typeColors[$t->form_type] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $typeOptions[$t->form_type] ?? $t->form_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs max-w-xs truncate">
                                {{ $t->description ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('settings.form-templates.edit', $t->id) }}"
                                   class="text-indigo-600 font-semibold hover:underline">
                                    {{ $t->lines_count }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleActive({{ $t->id }})"
                                        class="text-xs font-medium px-2 py-0.5 rounded-full transition
                                            {{ $t->is_active ? 'bg-green-100 text-green-700 hover:bg-red-100 hover:text-red-600' : 'bg-gray-100 text-gray-500 hover:bg-green-100 hover:text-green-600' }}">
                                    {{ $t->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('settings.form-templates.edit', $t->id) }}"
                                       class="px-2 py-1 text-xs text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded transition">
                                        Edit Items
                                    </a>
                                    <button wire:click="openEdit({{ $t->id }})"
                                            class="px-2 py-1 text-xs text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded transition">
                                        Rename
                                    </button>
                                    <button wire:click="delete({{ $t->id }})"
                                            wire:confirm="Delete template '{{ addslashes($t->name) }}'?"
                                            class="px-2 py-1 text-xs text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="py-16 text-center text-gray-400">
                <p class="text-4xl mb-3">📋</p>
                <p class="font-medium text-gray-500">No templates yet</p>
                <p class="text-xs mt-1">Create a template to pre-define item lists for stock takes, orders, or wastage entries.</p>
                <button wire:click="openCreate"
                        class="mt-4 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    Create First Template
                </button>
            </div>
        @endif
    </div>

    {{-- Create / Edit Modal --}}
    @if ($showModal)
        @teleport('body')
        <div class="fixed inset-0 z-50">
            <div class="fixed inset-0 bg-black/40" wire:click="closeModal"></div>
            <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 z-10">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-gray-800">
                        {{ $editingId ? 'Edit Template' : 'New Template' }}
                    </h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    {{-- Name --}}
                    <div>
                        <x-input-label value="Template Name *" />
                        <x-text-input wire:model="name" type="text" class="mt-1 block w-full"
                                      placeholder="e.g. Bar Section, Hot Kitchen, Pastry Kiosk" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    {{-- Form Type --}}
                    <div>
                        <x-input-label value="Form Type *" />
                        <select wire:model="form_type"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                {{ $editingId ? 'disabled' : '' }}>
                            <option value="">— Select Type —</option>
                            @foreach ($typeOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('form_type')" class="mt-1" />
                        @if ($editingId)
                            <p class="mt-1 text-xs text-gray-400">Form type cannot be changed after creation.</p>
                        @endif
                    </div>

                    {{-- Description --}}
                    <div>
                        <x-input-label value="Description" />
                        <x-text-input wire:model="description" type="text" class="mt-1 block w-full"
                                      placeholder="Optional notes about this template" />
                    </div>

                    {{-- Sort Order + Active --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Sort Order" />
                            <x-text-input wire:model="sort_order" type="number" min="0" max="9999"
                                          class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model="is_active"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                    <button wire:click="closeModal"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button wire:click="save"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        {{ $editingId ? 'Save Changes' : 'Create Template' }}
                    </button>
                </div>
            </div>
            </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
