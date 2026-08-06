<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('settings.index', ['module' => 'hr-people']) }}" class="text-gray-600 hover:text-gray-900 transition">
                <x-icon name="chevron-left" class="w-5 h-5" />
            </a>
            <div>
                <p class="page-eyebrow">Settings / HR</p>
                <h1 class="page-title mt-1">Public Holidays</h1>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="year" class="input w-auto">
                @foreach ($years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
            <button wire:click="openImport" class="btn-secondary">Import from calendar</button>
            <button wire:click="create" class="btn-primary">+ Add holiday</button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="panel p-4 mb-4">
        <p class="text-sm text-gray-700">
            The holidays this company recognises. Every active employee at a branch that observes a
            <strong>replaceable</strong> holiday earns one replacement day off for it, which they claim through
            Leave. Nothing here needs granting per person — the register is the entitlement.
        </p>
        <p class="text-xs text-gray-600 mt-1">
            A holiday marked <strong>paid, not replaced</strong> issues no day off: the company pays for the day
            worked at the public holiday rate instead. It still belongs in the register so staff can see the day
            was settled rather than lost.
        </p>
    </div>

    {{-- Company default claim window --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Default claim window</h3>
                <p class="text-sm text-gray-600 mt-0.5">
                    A replacement day has to be taken {{ $settings->windowLabel() }}.
                    Individual holidays can override it.
                </p>
            </div>
            <button wire:click="$set('showDefaults', {{ $showDefaults ? 'false' : 'true' }})" class="btn-ghost">
                {{ $showDefaults ? 'Cancel' : 'Change' }}
            </button>
        </div>

        @if ($showDefaults)
            <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="label">Replacement days must be taken</label>
                    <select wire:model.live="d_expiry_mode" class="input">
                        @foreach (\App\Models\LeaveSetting::EXPIRY_MODES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('d_expiry_mode')" class="mt-1" />
                </div>
                @if ($d_expiry_mode === \App\Models\LeaveSetting::EXPIRY_DAYS)
                    <div>
                        <label class="label">Days after the holiday</label>
                        <input type="number" min="1" max="730" wire:model="d_expiry_days" class="input" />
                        <p class="help">90 is the usual house rule — take it within the quarter.</p>
                        <x-input-error :messages="$errors->get('d_expiry_days')" class="mt-1" />
                    </div>
                @endif
                <div class="sm:col-span-3 flex justify-end">
                    <button wire:click="saveDefaults" class="btn-primary">Save window</button>
                </div>
            </div>
        @endif
    </div>

    {{-- Add / edit --}}
    @if ($showForm)
        <div class="card p-5 mb-4 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">{{ $editingId ? 'Edit' : 'New' }} public holiday</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="label">Date <span class="text-danger-500">*</span></label>
                    <input type="date" wire:model="f_date" class="input" />
                    <x-input-error :messages="$errors->get('f_date')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Name <span class="text-danger-500">*</span></label>
                    <input type="text" maxlength="255" wire:model="f_name" class="input" placeholder="e.g. Hari Raya Aidilfitri" />
                    <x-input-error :messages="$errors->get('f_name')" class="mt-1" />
                </div>
            </div>

            <div class="p-3 bg-gray-50 rounded-surface border border-gray-100 space-y-2">
                <label class="flex items-start gap-2">
                    <input type="checkbox" wire:model.live="f_replaceable" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm text-gray-700">
                        Staff who work this holiday get a replacement day off
                        <span class="block text-[11px] text-gray-500">
                            Untick when the company <strong>pays for the day instead</strong>, at the rate the
                            Employment Act sets for work on a public holiday. No replacement day is issued and
                            the apply form says why.
                        </span>
                    </span>
                </label>
                @unless ($f_replaceable)
                    <p class="text-[11px] text-warning-700 pl-6">
                        This holiday will issue no replacement days. Anyone who has already booked one against it
                        keeps that day — switching it off does not take back leave already approved.
                    </p>
                @endunless
            </div>

            @if ($f_replaceable)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="label">Claim window</label>
                        <select wire:model.live="f_expiry_mode" class="input">
                            <option value="">Use the company default — {{ $settings->windowLabel() }}</option>
                            @foreach (\App\Models\LeaveSetting::EXPIRY_MODES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="help">Bounds the date the day off is taken, not when it is applied for.</p>
                        <x-input-error :messages="$errors->get('f_expiry_mode')" class="mt-1" />
                    </div>
                    @if ($f_expiry_mode === \App\Models\LeaveSetting::EXPIRY_DAYS)
                        <div>
                            <label class="label">Days after the holiday</label>
                            <input type="number" min="1" max="730" wire:model="f_expiry_days" class="input" />
                            <x-input-error :messages="$errors->get('f_expiry_days')" class="mt-1" />
                        </div>
                    @endif
                </div>
            @endif

            <div class="p-3 bg-gray-50 rounded-surface border border-gray-100 space-y-2">
                <label class="flex items-start gap-2">
                    <input type="checkbox" wire:model.live="f_all_outlets" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span class="text-sm text-gray-700">
                        Observed at every branch
                        <span class="block text-[11px] text-gray-500">
                            Leave this ticked for a federal holiday. Untick for a state one — Thaipusam is
                            Selangor's, not Johor's — and pick the branches that close.
                        </span>
                    </span>
                </label>

                @unless ($f_all_outlets)
                    <div class="pl-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1">
                        @foreach ($outlets as $outlet)
                            <label class="inline-flex items-center gap-2" wire:key="ph-out-{{ $outlet->id }}">
                                <input type="checkbox" value="{{ $outlet->id }}" wire:model="f_outlet_ids"
                                       class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="text-sm text-gray-700">{{ $outlet->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('f_outlet_ids')" class="mt-1 pl-6" />
                @endunless
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="label">Note</label>
                    <input type="text" maxlength="255" wire:model="f_notes" class="input" placeholder="Optional — shown to HR only" />
                    <x-input-error :messages="$errors->get('f_notes')" class="mt-1" />
                </div>
                <div class="flex items-end pb-1">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" wire:model="f_is_active" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                        <span class="text-sm text-gray-700">Active</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button wire:click="$set('showForm', false)" class="btn-ghost">Cancel</button>
                <button wire:click="save" class="btn-primary">Save</button>
            </div>
        </div>
    @endif

    {{-- Import --}}
    @if ($showImport)
        <div class="card p-5 mb-4 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">Import {{ $year }} holidays from the calendar</h3>
            <p class="text-xs text-gray-600">
                Copied from Settings &rsaquo; Calendar Events, not linked to it — that calendar exists to explain
                sales, and an entitlement should not disappear because someone tidied it. Everything imports as
                replaceable and company-wide; correct the exceptions afterwards.
            </p>

            @if ($importNotice)
                <div class="alert-info">{{ $importNotice }}</div>
            @else
                <div class="max-h-80 overflow-y-auto border border-gray-100 rounded-surface divide-y divide-gray-100">
                    @foreach ($importRows as $i => $row)
                        <label class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50" wire:key="imp-{{ $i }}">
                            <input type="checkbox" wire:model="importRows.{{ $i }}.selected"
                                   class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                            <span class="text-xs tabular-nums text-gray-600 w-24">
                                {{ \Carbon\Carbon::parse($row['date'])->format('j M Y') }}
                            </span>
                            <span class="text-sm text-gray-800">{{ $row['name'] }}</span>
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <button wire:click="$set('showImport', false)" class="btn-ghost">Close</button>
                @unless ($importNotice)
                    <button wire:click="runImport" class="btn-primary">Import selected</button>
                @endunless
            </div>
        </div>
    @endif

    {{-- Register --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[900px]">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-2 py-2 text-left">Holiday</th>
                        <th class="px-2 py-2 text-left">Branches</th>
                        <th class="px-2 py-2 text-left">Replacement</th>
                        <th class="px-2 py-2 text-right">Taken</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($holidays as $holiday)
                        <tr wire:key="ph-{{ $holiday->id }}" class="hover:bg-gray-50 {{ $holiday->is_active ? '' : 'opacity-50' }}">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="tabular-nums text-gray-800">{{ $holiday->date->format('j M Y') }}</span>
                                <span class="block text-[11px] text-gray-500">{{ $holiday->date->format('l') }}</span>
                            </td>
                            <td class="px-2 py-2">
                                <span class="font-medium text-gray-800">{{ $holiday->name }}</span>
                                @if ($holiday->notes)
                                    <span class="block text-[11px] text-gray-500">{{ $holiday->notes }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                @if ($holiday->isCompanyWide())
                                    <span class="badge-info">All branches</span>
                                @else
                                    <span class="text-xs text-gray-700">{{ $holiday->outlets->pluck('name')->implode(', ') }}</span>
                                @endif
                            </td>
                            <td class="px-2 py-2">
                                @if ($holiday->is_replaceable)
                                    <span class="badge-success">1 day off</span>
                                    <span class="block text-[11px] text-gray-600 mt-0.5">
                                        {{ $holiday->windowLabel($settings) }}
                                    </span>
                                @else
                                    <span class="badge-warning">Paid, not replaced</span>
                                    <span class="block text-[11px] text-gray-600 mt-0.5">Paid at the public holiday rate</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right tabular-nums text-gray-600">{{ $holiday->requests_count }}</td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                                <button wire:click="edit({{ $holiday->id }})" class="text-xs font-medium text-brand-600 hover:text-brand-800">Edit</button>
                                <button wire:click="toggleActive({{ $holiday->id }})"
                                        class="ml-3 text-xs font-medium text-gray-600 hover:text-gray-900">
                                    {{ $holiday->is_active ? 'Switch off' : 'Switch on' }}
                                </button>
                                @if ($holiday->requests_count === 0)
                                    <button wire:click="delete({{ $holiday->id }})" wire:confirm="Remove this holiday from the register?"
                                            class="ml-3 text-xs font-medium text-danger-600 hover:text-danger-800">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8">
                                <div class="empty-state">
                                    <p class="text-sm text-gray-700">No public holidays recorded for {{ $year }}.</p>
                                    <p class="text-xs text-gray-600 mt-1">
                                        Until there are, nobody can claim a replacement day —
                                        <button wire:click="openImport" class="text-brand-600 hover:text-brand-800 font-medium">import them from the calendar</button>
                                        or add them by hand.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($holidays->isNotEmpty())
        <p class="text-xs text-gray-600 mt-3">
            {{ $replaceableCount }} of {{ $holidays->count() }} {{ $year }} holidays earn a replacement day.
        </p>
    @endif
</div>
