<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
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
        <div>
            <p class="text-xs text-gray-600">HR / Shifts</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Shifts</h2>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('hr.duty-roster') }}" class="btn-secondary">Duty Roster</a>
            <button wire:click="openCreate" class="btn-primary">
                <span class="sm:hidden">+ Add</span>
                <span class="hidden sm:inline">+ Add Shift</span>
            </button>
        </div>
    </div>

    <div class="card p-5 mb-4">
        <p class="text-sm text-gray-600">
            Named start and end times you can drop onto a duty roster in one click, instead of typing the same
            hours into every cell. Give a shift a section and it only appears for that section — BOH mornings
            stay out of the front-of-house list.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            Applying a shift copies its times onto the roster entry. Editing a shift later does not change
            rosters that were already built.
        </p>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-surface min-w-[900px]">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Shift</th>
                    <th class="px-4 py-3 text-left w-40">Time</th>
                    <th class="px-4 py-3 text-right w-24">Break</th>
                    <th class="px-4 py-3 text-right w-24">Paid hrs</th>
                    <th class="px-4 py-3 text-left w-40">Applies to</th>
                    <th class="px-4 py-3 text-center w-24">On rosters</th>
                    <th class="px-4 py-3 text-center w-28">Status</th>
                    <th class="px-4 py-3 text-center w-32">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shifts as $shift)
                    <tr wire:key="shift-{{ $shift->id }}" class="hover:bg-gray-50 {{ ! $shift->is_active ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if ($shift->code)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-brand-100 text-brand-700">{{ $shift->code }}</span>
                                @endif
                                <span class="font-medium text-gray-800">{{ $shift->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-700 tabular-nums">{{ $shift->timeLabel() }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 tabular-nums">{{ $shift->rest_duration }} min</td>
                        <td class="px-4 py-3 text-right text-gray-600 tabular-nums">{{ number_format($shift->paidHours(), 2) }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            {{ $shift->outlet?->name ?? 'All outlets' }}
                            <span class="text-gray-400">·</span>
                            {{ $shift->section?->name ?? 'All sections' }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $usage[$shift->id] ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $shift->is_active ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $shift->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <button wire:click="openEdit({{ $shift->id }})" title="Edit" class="text-brand-500 hover:text-brand-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="toggleActive({{ $shift->id }})" title="{{ $shift->is_active ? 'Deactivate' : 'Activate' }}" class="text-warning-500 hover:text-warning-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button wire:click="delete({{ $shift->id }})" data-confirm-delete="Delete this shift?" title="Delete" class="text-danger-400 hover:text-danger-600 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-600">No shifts yet. Add one and it becomes a one-click option on the duty roster.</td></tr>
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
                        <h3 class="text-sm font-semibold text-gray-800">{{ $editingId ? 'Edit Shift' : 'Add Shift' }}</h3>
                        <button @click="open = false" class="text-gray-600 hover:text-gray-900 p-1">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form wire:submit.prevent="save" class="p-5 space-y-3">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="text-xs font-semibold text-gray-600">Name <span class="text-danger-500">*</span></label>
                                <input type="text" wire:model="name" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. Morning, Split, Closing" />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Code</label>
                                <input type="text" wire:model="code" maxlength="12" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="AM" />
                                <p class="mt-1 text-[11px] text-gray-500">Shown in the grid.</p>
                                <x-input-error :messages="$errors->get('code')" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Start <span class="text-danger-500">*</span></label>
                                <input type="time" wire:model="start_time" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('start_time')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">End <span class="text-danger-500">*</span></label>
                                <input type="time" wire:model="end_time" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <p class="mt-1 text-[11px] text-gray-500">Earlier than start = overnight.</p>
                                <x-input-error :messages="$errors->get('end_time')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Break (min)</label>
                                <input type="number" min="0" max="480" wire:model="rest_duration" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('rest_duration')" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Outlet</label>
                                <select wire:model="outlet_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    <option value="">All outlets</option>
                                    @foreach ($outlets as $o)
                                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('outlet_id')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Section</label>
                                <select wire:model="section_id" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    <option value="">All sections</option>
                                    @foreach ($sections as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('section_id')" class="mt-1" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Normal hours</label>
                                <input type="number" step="0.25" min="0" max="24" wire:model="normal_hours" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <p class="mt-1 text-[11px] text-gray-500">Blank = the outlet's roster default.</p>
                                <x-input-error :messages="$errors->get('normal_hours')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Sort Order</label>
                                <input type="number" wire:model="sort_order" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2">
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
