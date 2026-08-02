<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-6">
        <p class="page-eyebrow">Labels / Staff access</p>
        <h1 class="page-title mt-1">Staff access</h1>
    </div>

    <div class="card p-5 mb-4">
        <p class="text-sm text-gray-600">
            Give kitchen staff a PIN so they can print labels from a phone or tablet without a Servora login.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            They go to <span class="font-mono text-gray-700">{{ $appUrl }}</span>, tap their name and enter their PIN.
            Whoever signs in is recorded as "Prepared by" on every label they print.
        </p>
        <p class="text-xs text-warning-600 mt-2">
            A PIN is shown once when you issue it and stored encrypted after that — it can't be looked up later,
            only replaced. Staff can change their own PIN in the app.
        </p>
    </div>

    <div class="card p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name…"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                <select wire:model.live="outletFilter" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-[680px] divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Staff</th>
                        <th class="px-4 py-3 text-left">Outlet</th>
                        <th class="px-4 py-3 text-left w-40">Label access</th>
                        <th class="px-4 py-3 text-center w-64">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($employees as $employee)
                        <tr class="hover:bg-gray-50" wire:key="emp-{{ $employee->id }}">
                            <td class="px-4 py-3 font-medium text-gray-700">{{ $employee->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $employee->outlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if (isset($justIssued[$employee->id]))
                                    <span class="inline-flex items-center gap-2">
                                        <span class="px-2 py-1 rounded bg-brand-50 text-brand-700 font-mono text-base tracking-widest">
                                            {{ $justIssued[$employee->id] }}
                                        </span>
                                        <span class="text-[10px] text-warning-600">shown once</span>
                                    </span>
                                @elseif ($employee->hasLabelPin())
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-success-50 text-success-700">
                                        PIN set
                                    </span>
                                    <span class="block text-[10px] text-gray-600 mt-0.5">
                                        {{ $employee->label_pin_set_at?->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">
                                        No access
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($employee->outlet_id)
                                    <button wire:click="issuePin({{ $employee->id }})"
                                            class="px-2.5 py-1.5 text-xs text-white bg-brand-600 rounded-lg hover:bg-brand-700">
                                        {{ $employee->hasLabelPin() ? 'Reset PIN' : 'Issue PIN' }}
                                    </button>
                                    <button wire:click="openManual({{ $employee->id }})"
                                            class="ml-1 text-xs text-brand-600 hover:underline">Set manually</button>
                                    @if ($employee->hasLabelPin())
                                        <button wire:click="revoke({{ $employee->id }})"
                                                wire:confirm="Revoke label access for {{ $employee->name }}? They'll be signed out everywhere."
                                                class="ml-2 text-xs text-danger-500 hover:underline">Revoke</button>
                                    @endif
                                @else
                                    {{-- Labels are outlet-scoped: without one there is no
                                         printer, no sets and nothing to expire. --}}
                                    <span class="text-xs text-gray-600">Needs an outlet first</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-gray-600 text-sm">No staff found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div x-data="{ open: @entangle('settingFor').live }">
        <template x-teleport="body">
            <div x-show="open" x-cloak @keydown.escape.window="$wire.closeManual()"
                 class="fixed inset-0 z-[100] overflow-y-auto">
                <div class="fixed inset-0 bg-black/50" @click="$wire.closeManual()"></div>
                <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm" @click.stop>
                        <div class="px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Set a PIN</h3>
                        </div>
                        <form wire:submit.prevent="saveManualPin" class="p-5 space-y-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">PIN (4–6 digits)</label>
                                <input type="text" inputmode="numeric" maxlength="6" wire:model="manualPin"
                                       class="mt-1 w-full text-lg tracking-widest rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('manualPin')" class="mt-1" />
                            </div>
                            <p class="text-xs text-gray-600">
                                Issuing a random PIN is safer than choosing one — staff can change it themselves afterwards.
                            </p>
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                <button type="button" @click="$wire.closeManual()"
                                        class="btn-secondary">Cancel</button>
                                <button type="submit" class="px-4 py-2 text-sm text-white bg-brand-600 rounded-lg hover:bg-brand-700">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
