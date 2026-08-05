<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('settings.index') }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="page-eyebrow">Settings / Pay Components</p>
                <h1 class="page-title mt-1">Pay Components</h1>
            </div>
        </div>
        <button wire:click="openCreate" class="btn-primary">
            <span class="sm:hidden">+ Add</span>
            <span class="hidden sm:inline">+ Add Component</span>
        </button>
    </div>

    {{-- Overtime rates --}}
    <div class="card p-5 mb-4">
        <h3 class="text-sm font-semibold text-gray-700">Overtime rates</h3>
        <p class="text-xs text-gray-500 mt-1">
            Used to price approved OT claims on the Compensation screen. Defaults follow the Malaysian Employment Act —
            ordinary rate of pay is the monthly salary over 26 days, hourly is that over 8 hours.
        </p>
        <form wire:submit.prevent="saveRates" class="mt-4 grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div>
                <label class="text-xs font-semibold text-gray-600">Normal day ×</label>
                <input type="number" step="0.01" min="0" wire:model="ot_normal" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('ot_normal')" class="mt-1" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Rest day ×</label>
                <input type="number" step="0.01" min="0" wire:model="ot_rest_day" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('ot_rest_day')" class="mt-1" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Public holiday ×</label>
                <input type="number" step="0.01" min="0" wire:model="ot_public_holiday" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('ot_public_holiday')" class="mt-1" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Days / month</label>
                <input type="number" min="1" max="31" wire:model="monthly_days" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('monthly_days')" class="mt-1" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600">Hours / day</label>
                <input type="number" step="0.25" min="1" max="24" wire:model="daily_hours" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                <x-input-error :messages="$errors->get('daily_hours')" class="mt-1" />
            </div>
            <div class="col-span-2 sm:col-span-5 flex justify-end">
                <button type="submit" class="btn-primary">Save rates</button>
            </div>
        </form>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-surface min-w-[860px]">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-center w-28">Type</th>
                    <th class="px-4 py-3 text-center w-40">Calculation</th>
                    <th class="px-4 py-3 text-right w-28">Default</th>
                    <th class="px-4 py-3 text-center w-24">Assigned</th>
                    <th class="px-4 py-3 text-center w-28">Status</th>
                    <th class="px-4 py-3 text-center w-32">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($components as $c)
                    <tr wire:key="pc-{{ $c->id }}" class="hover:bg-gray-50 {{ ! $c->is_active ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $c->name }}</p>
                            @if ($c->description)
                                <p class="text-xs text-gray-600 mt-0.5">{{ $c->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c->isDeduction() ? 'bg-danger-100 text-danger-700' : 'bg-success-100 text-success-700' }}">
                                {{ \App\Models\PayComponent::KINDS[$c->kind] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-gray-600">{{ \App\Models\PayComponent::CALCULATIONS[$c->calculation] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-600">
                            {{ $c->default_amount !== null ? number_format((float) $c->default_amount, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $usage[$c->id] ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c->is_active ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $c->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <button wire:click="openEdit({{ $c->id }})" title="Edit" class="text-brand-500 hover:text-brand-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="toggleActive({{ $c->id }})" title="{{ $c->is_active ? 'Deactivate' : 'Activate' }}" class="text-warning-500 hover:text-warning-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button wire:click="delete({{ $c->id }})" wire:confirm="Delete this pay component?" title="Delete" class="text-danger-400 hover:text-danger-600 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-600">No pay components yet. Add an allowance or deduction to assign it to staff.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    <div x-data="{ open: @entangle('showModal') }">
    <template x-teleport="body">
        <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
            <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $editingId ? 'Edit Pay Component' : 'Add Pay Component' }}</h3>
                        <button @click="open = false" class="text-gray-600 hover:text-gray-900 p-1">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form wire:submit.prevent="save" class="p-5 space-y-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Name <span class="text-danger-500">*</span></label>
                            <input type="text" wire:model="name" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. Travel Allowance, Uniform Deduction" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Description</label>
                            <input type="text" wire:model="description" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('description')" class="mt-1" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Type <span class="text-danger-500">*</span></label>
                                <select wire:model="kind" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    @foreach (\App\Models\PayComponent::KINDS as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('kind')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Calculation <span class="text-danger-500">*</span></label>
                                <select wire:model="calculation" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    @foreach (\App\Models\PayComponent::CALCULATIONS as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('calculation')" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Default amount</label>
                                <input type="number" step="0.01" min="0" wire:model="default_amount" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <p class="mt-1 text-[11px] text-gray-500">Suggested when assigning; each employee can differ.</p>
                                <x-input-error :messages="$errors->get('default_amount')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Sort Order</label>
                                <input type="number" wire:model="sort_order" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="is_taxable" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                            <span class="text-sm text-gray-700">Taxable</span>
                        </label>
                        <label class="inline-flex items-center gap-2 ml-4">
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
