<div class="space-y-3">
    @if (session('success'))
        <div class="rounded-surface bg-success-50 border border-success-200 px-3 py-2.5 text-sm text-success-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Balances. The first thing anyone opens this screen to see. --}}
    <div class="card p-3">
        <div class="flex items-baseline justify-between gap-2 mb-2">
            <h2 class="text-sm font-semibold text-gray-800">My leave — {{ $year }}</h2>
            <span class="text-[11px] text-gray-500">days left</span>
        </div>

        <div class="divide-y divide-gray-100">
            @foreach ($balances as $row)
                <div class="py-2 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $row['type']->name }}</p>
                        <p class="text-[11px] text-gray-500">
                            {{ rtrim(rtrim(number_format($row['entitled'], 1), '0'), '.') }} entitled ·
                            {{ rtrim(rtrim(number_format($row['taken'], 1), '0'), '.') }} taken
                            @if ($row['pending'] > 0)
                                · {{ rtrim(rtrim(number_format($row['pending'], 1), '0'), '.') }} pending
                            @endif
                        </p>
                        {{-- An entitlement paid out with salary is SHOWN, with
                             the reason — hiding it would leave someone unable to
                             see days they are owed. --}}
                        @if ($row['blocked'])
                            <p class="text-[11px] text-warning-700 mt-0.5">{{ $row['blocked'] }}</p>
                        @endif
                    </div>
                    <span class="text-lg font-semibold tabular-nums shrink-0
                                 {{ $row['remaining'] > 0 ? 'text-brand-700' : 'text-gray-400' }}">
                        {{ rtrim(rtrim(number_format($row['remaining'], 1), '0'), '.') }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    @unless ($showForm)
        {{-- 44px minimum: this is tapped one-handed on a phone. --}}
        <button wire:click="openForm"
                class="w-full min-h-[3rem] rounded-control bg-brand-600 text-white font-semibold text-sm active:bg-brand-700">
            Apply for leave
        </button>
    @endunless

    {{-- Apply --}}
    @if ($showForm)
        <div class="card p-3 space-y-3">
            <h3 class="text-sm font-semibold text-gray-800">Apply for leave</h3>

            <div>
                <label class="text-xs font-semibold text-gray-600">Type</label>
                <select wire:model.live="f_type" class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300">
                    <option value="">— Select —</option>
                    @foreach ($balances as $row)
                        <option value="{{ $row['type']->id }}" @disabled($row['blocked'] !== null)>
                            {{ $row['type']->name }}
                            ({{ rtrim(rtrim(number_format($row['remaining'], 1), '0'), '.') }} left)
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('f_type')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs font-semibold text-gray-600">From</label>
                    <input type="date" wire:model.live="f_start"
                           class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300" />
                    <x-input-error :messages="$errors->get('f_start')" class="mt-1" />
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">To</label>
                    <input type="date" wire:model.live="f_end" @disabled($f_half)
                           class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300 disabled:bg-gray-100" />
                    <x-input-error :messages="$errors->get('f_end')" class="mt-1" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Days</label>
                    <input type="number" step="0.5" min="0.5" wire:model.live.debounce.500ms="f_days"
                           class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300" />
                    <p class="mt-1 text-[11px] text-gray-500">The end date follows this. Adjust for rest days.</p>
                    <x-input-error :messages="$errors->get('f_days')" class="mt-1" />
                </div>
                <div class="flex flex-col justify-start pt-6 gap-2">
                    <label class="inline-flex items-center gap-2 min-h-[2.75rem]">
                        <input type="checkbox" wire:model.live="f_half"
                               class="h-5 w-5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                        <span class="text-sm text-gray-700">Half day</span>
                    </label>
                    @if ($f_half)
                        <select wire:model="f_half_period"
                                class="w-full min-h-[2.75rem] text-sm rounded-control border-gray-300">
                            <option value="am">Morning</option>
                            <option value="pm">Afternoon</option>
                        </select>
                    @endif
                </div>
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600">Reason</label>
                <input type="text" maxlength="500" wire:model="f_reason"
                       class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300" />
                <x-input-error :messages="$errors->get('f_reason')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <button wire:click="$set('showForm', false)"
                        class="min-h-[2.75rem] rounded-control border border-gray-300 text-sm font-medium text-gray-700 active:bg-gray-50">
                    Cancel
                </button>
                <button wire:click="apply"
                        class="min-h-[2.75rem] rounded-control bg-brand-600 text-white text-sm font-semibold active:bg-brand-700">
                    Submit
                </button>
            </div>
        </div>
    @endif

    {{-- My requests --}}
    <div class="card p-3">
        <h3 class="text-sm font-semibold text-gray-800 mb-2">My applications</h3>

        @forelse ($requests as $req)
            <div wire:key="slr-{{ $req->id }}" class="py-2.5 border-t border-gray-100 first:border-t-0">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800">
                            {{ $req->leaveType?->name ?? 'Leave' }}
                            <span class="text-gray-500 font-normal">
                                · {{ rtrim(rtrim(number_format((float) $req->days, 1), '0'), '.') }} day(s)
                            </span>
                        </p>
                        <p class="text-[11px] text-gray-600">
                            {{ $req->start_date->format('d M Y') }}
                            @if (! $req->start_date->isSameDay($req->end_date))
                                – {{ $req->end_date->format('d M Y') }}
                            @endif
                            @if ($req->is_half_day) · half day ({{ $req->half_day_period }}) @endif
                        </p>
                        @if ($req->reason)
                            <p class="text-[11px] text-gray-500 mt-0.5">{{ $req->reason }}</p>
                        @endif
                        @if ($req->decision_note)
                            <p class="text-[11px] text-gray-500 mt-0.5 italic">{{ $req->decision_note }}</p>
                        @endif
                    </div>
                    <div class="text-right shrink-0">
                        @php
                            $tone = match ($req->status) {
                                \App\Models\LeaveRequest::APPROVED  => 'bg-success-100 text-success-700',
                                \App\Models\LeaveRequest::REJECTED  => 'bg-danger-100 text-danger-700',
                                \App\Models\LeaveRequest::CANCELLED => 'bg-gray-100 text-gray-600',
                                default                             => 'bg-warning-100 text-warning-700',
                            };
                        @endphp
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium {{ $tone }}">
                            {{ $req->statusLabel() }}
                        </span>
                        @if (in_array($req->status, ['pending', 'approved'], true))
                            <button wire:click="cancel({{ $req->id }})"
                                    wire:confirm="Cancel this leave application?"
                                    class="block mt-1.5 text-[11px] font-medium text-gray-600 active:text-gray-900">
                                Cancel
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="py-4 text-center text-sm text-gray-500">You have not applied for any leave yet.</p>
        @endforelse
    </div>
</div>
