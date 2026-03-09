<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Back + Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / Suppliers</p>
        </div>
        <button wire:click="openCreate"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            + Add Supplier
        </button>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by name, code or contact…"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <div>
                <select wire:model.live="statusFilter"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">Supplier</th>
                    <th class="px-4 py-3 text-left">Contact</th>
                    <th class="px-4 py-3 text-left">Phone / Email</th>
                    <th class="px-4 py-3 text-left">Payment Terms</th>
                    <th class="px-4 py-3 text-center">Ingredients</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($suppliers as $supplier)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">{{ $supplier->name }}</div>
                            @if ($supplier->code)
                                <div class="text-xs text-gray-400">{{ $supplier->code }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $supplier->contact_person ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($supplier->phone)
                                <div class="text-gray-600">{{ $supplier->phone }}</div>
                            @endif
                            @if ($supplier->email)
                                <div class="text-xs text-gray-400">{{ $supplier->email }}</div>
                            @endif
                            @if (! $supplier->phone && ! $supplier->email)
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $supplier->payment_terms ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-gray-700 font-medium">{{ $supplier->ingredients_count }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($supplier->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <button wire:click="openEdit({{ $supplier->id }})" title="Edit"
                                        class="text-indigo-500 hover:text-indigo-700 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button wire:click="toggleActive({{ $supplier->id }})"
                                        title="{{ $supplier->is_active ? 'Deactivate' : 'Activate' }}"
                                        class="{{ $supplier->is_active ? 'text-green-500 hover:text-green-700' : 'text-gray-400 hover:text-gray-600' }} transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </button>
                                <button wire:click="delete({{ $supplier->id }})"
                                        wire:confirm="Delete '{{ $supplier->name }}'? This cannot be undone."
                                        title="Delete"
                                        class="text-red-400 hover:text-red-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <div class="text-3xl mb-2">🏭</div>
                            <p class="font-medium">No suppliers found</p>
                            <p class="text-xs mt-1">Add your first supplier to get started.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($suppliers->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>

    {{-- Modal --}}
    <div x-data="{}" x-show="$wire.showModal" x-cloak
         class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-2xl z-10">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">
                    @if ($editingId) Edit: {{ $name }} @else New Supplier @endif
                </h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit="save">
                <div class="px-6 py-5 space-y-4">

                    {{-- Name --}}
                    <div>
                        <x-input-label for="s_name" value="Supplier Name *" />
                        <x-text-input id="s_name" wire:model="name" type="text" class="mt-1 block w-full" placeholder="e.g. ABC Fresh Seafood" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    {{-- Code | Payment Terms --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="s_code" value="Code" />
                            <x-text-input id="s_code" wire:model="code" type="text" class="mt-1 block w-full" placeholder="e.g. SUP-001" />
                            <x-input-error :messages="$errors->get('code')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="s_payment" value="Payment Terms" />
                            <select id="s_payment" wire:model="payment_terms"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— select —</option>
                                <option>Cash on Delivery</option>
                                <option>Prepayment</option>
                                <option>Net 7</option>
                                <option>Net 14</option>
                                <option>Net 30</option>
                                <option>Net 60</option>
                            </select>
                            <x-input-error :messages="$errors->get('payment_terms')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Contact Person --}}
                    <div>
                        <x-input-label for="s_contact" value="Contact Person" />
                        <x-text-input id="s_contact" wire:model="contact_person" type="text" class="mt-1 block w-full" placeholder="e.g. Ahmad bin Ali" />
                        <x-input-error :messages="$errors->get('contact_person')" class="mt-1" />
                    </div>

                    {{-- Phone | Email --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="s_phone" value="Phone" />
                            <x-text-input id="s_phone" wire:model="phone" type="text" class="mt-1 block w-full" placeholder="e.g. 012-345 6789" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="s_email" value="Email" />
                            <x-text-input id="s_email" wire:model="email" type="email" class="mt-1 block w-full" placeholder="e.g. orders@supplier.com" />
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Address --}}
                    <div>
                        <x-input-label for="s_address" value="Address" />
                        <textarea id="s_address" wire:model="address" rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Street, City, State"></textarea>
                        <x-input-error :messages="$errors->get('address')" class="mt-1" />
                    </div>

                    {{-- Is Active --}}
                    <div>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700 font-medium">Active</span>
                        </label>
                    </div>

                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <button type="button" @click="$wire.closeModal()"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Save Supplier
                    </button>
                </div>
            </form>

        </div>
        </div>
        </div>
    </div>
</div>
