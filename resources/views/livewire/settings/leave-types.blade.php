<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('settings.index', ['module' => 'hr-people']) }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="page-eyebrow">Settings / HR</p>
                <h1 class="page-title mt-1">Leave Types</h1>
            </div>
        </div>
        <button wire:click="create" class="btn-primary">+ Add leave type</button>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="panel p-4 mb-4">
        <p class="text-sm text-gray-700">
            The kinds of leave this company grants. Each one carries its own yearly entitlement, which can
            then be overridden per employee.
        </p>
        <p class="text-xs text-gray-600 mt-1">
            <strong>Can be applied for</strong> is the setting to watch. A replacement public holiday that is
            <strong>paid out with salary</strong> still needs its entitlement recorded and reported, but nobody
            should be able to book it as a day off — untick it and the apply form says exactly that.
        </p>
    </div>

    @if ($showForm)
        <div class="card p-5 mb-4 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">{{ $editingId ? 'Edit' : 'New' }} leave type</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="label">Name <span class="text-danger-500">*</span></label>
                    <input type="text" wire:model="f_name" class="input" placeholder="e.g. Annual Leave" />
                    <x-input-error :messages="$errors->get('f_name')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Code <span class="text-danger-500">*</span></label>
                    <input type="text" maxlength="20" wire:model="f_code" class="input uppercase" placeholder="AL" />
                    <p class="help">Short label used on rosters and reports.</p>
                    <x-input-error :messages="$errors->get('f_code')" class="mt-1" />
                </div>
            </div>

            <div>
                <label class="label">Description</label>
                <input type="text" maxlength="500" wire:model="f_description" class="input"
                       placeholder="Shown to staff on the apply form" />
                <x-input-error :messages="$errors->get('f_description')" class="mt-1" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="label">Default days a year</label>
                    <input type="number" step="0.5" min="0" wire:model="f_default_days" class="input" />
                    <p class="help">Used when an employee has no entitlement set of their own.</p>
                    <x-input-error :messages="$errors->get('f_default_days')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Colour</label>
                    <select wire:model="f_colour" class="input">
                        @foreach (\App\Livewire\Settings\LeaveTypes::COLOURS as $c)
                            <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="f_is_active" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                </div>
            </div>

            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 space-y-2">
                <label class="flex items-start gap-2">
                    <input type="checkbox" wire:model.live="f_is_claimable" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm text-gray-700">
                        Staff can apply for this leave
                        <span class="block text-[11px] text-gray-500">
                            Untick for an entitlement that is <strong>paid out with salary</strong> rather than taken
                            as time away — a replacement public holiday, typically. The balance is still recorded
                            and reported; it simply cannot be booked.
                        </span>
                    </span>
                </label>
                @unless ($f_is_claimable)
                    <p class="text-[11px] text-warning-700 pl-6">
                        The apply form will show this type greyed out with that reason, rather than hiding it —
                        staff can still see the entitlement they have.
                    </p>
                @endunless

                <label class="flex items-start gap-2">
                    <input type="checkbox" wire:model="f_is_paid" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm text-gray-700">Paid leave
                        <span class="block text-[11px] text-gray-500">Untick for unpaid leave.</span>
                    </span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" wire:model="f_requires_approval" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm text-gray-700">Needs approval</span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" wire:model="f_allows_half_day" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm text-gray-700">Half days allowed</span>
                </label>

                <label class="flex items-start gap-2">
                    <input type="checkbox" wire:model.live="f_carry_forward" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm text-gray-700">Unused days carry into next year</span>
                </label>
                @if ($f_carry_forward)
                    <div class="pl-6 sm:w-1/3">
                        <label class="label">Carry-forward cap (days)</label>
                        <input type="number" step="0.5" min="0" wire:model="f_carry_forward_cap" class="input" placeholder="no cap" />
                        <x-input-error :messages="$errors->get('f_carry_forward_cap')" class="mt-1" />
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <button wire:click="$set('showForm', false)" class="btn-ghost">Cancel</button>
                <button wire:click="save" class="btn-primary">Save</button>
            </div>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[860px]">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Type</th>
                        <th class="px-2 py-2 text-left">Code</th>
                        <th class="px-2 py-2 text-right">Default days</th>
                        <th class="px-2 py-2 text-left">Rules</th>
                        <th class="px-2 py-2 text-right">Used</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr wire:key="lt-{{ $type->id }}" class="hover:bg-gray-50 {{ $type->is_active ? '' : 'opacity-50' }}">
                            <td class="px-3 py-2">
                                <span class="font-medium text-gray-800">{{ $type->name }}</span>
                                @if ($type->description)
                                    <span class="block text-[11px] text-gray-500">{{ $type->description }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-2"><span class="badge-info font-mono">{{ $type->code }}</span></td>
                            <td class="px-2 py-2 text-right tabular-nums text-gray-700">
                                {{ rtrim(rtrim(number_format((float) $type->default_days, 1), '0'), '.') }}
                            </td>
                            <td class="px-2 py-2">
                                <div class="flex flex-wrap gap-1">
                                    @unless ($type->is_claimable)
                                        <span class="badge-warning" title="Paid out with salary — cannot be booked as a day off">paid with salary</span>
                                    @endunless
                                    @unless ($type->is_paid)<span class="badge-danger">unpaid</span>@endunless
                                    @if ($type->carry_forward)
                                        <span class="badge-info">carries forward{{ $type->carry_forward_cap !== null ? ' (max ' . rtrim(rtrim(number_format((float) $type->carry_forward_cap, 1), '0'), '.') . ')' : '' }}</span>
                                    @endif
                                    @unless ($type->requires_approval)<span class="badge-success">auto-approved</span>@endunless
                                    @if ($type->allows_half_day)<span class="badge-info">half days</span>@endif
                                </div>
                            </td>
                            <td class="px-2 py-2 text-right tabular-nums text-gray-600">{{ $type->requests_count }}</td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                                <button wire:click="edit({{ $type->id }})" class="text-xs font-medium text-brand-600 hover:text-brand-800">Edit</button>
                                <button wire:click="toggleActive({{ $type->id }})"
                                        class="ml-3 text-xs font-medium text-gray-600 hover:text-gray-900">
                                    {{ $type->is_active ? 'Switch off' : 'Switch on' }}
                                </button>
                                @if ($type->requests_count === 0)
                                    <button wire:click="delete({{ $type->id }})" wire:confirm="Delete this leave type?"
                                            class="ml-3 text-xs font-medium text-danger-600 hover:text-danger-800">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-sm text-gray-600">No leave types yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
