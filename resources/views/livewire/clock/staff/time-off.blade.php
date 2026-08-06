<div class="space-y-3">
    @if (session('success'))
        <div class="rounded-surface bg-success-50 border border-success-200 px-3 py-2.5 text-sm text-success-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- The balance, in hours AND days: a working day here is 7.5, 9 or 11
         hours depending on the contract, so hours alone do not answer
         "is that an afternoon or a whole day". --}}
    <div class="card p-4 text-center">
        <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Overtime available as time off</p>
        <p class="mt-1 text-3xl font-semibold tabular-nums text-brand-700">
            {{ rtrim(rtrim(number_format($available, 2), '0'), '.') }}
            <span class="text-base font-normal text-gray-500">hours</span>
        </p>
        <p class="text-xs text-gray-600 mt-0.5">
            {{ rtrim(rtrim(number_format($inDays, 2), '0'), '.') }} day(s)
            on your {{ rtrim(rtrim(number_format($dayHours, 2), '0'), '.') }}-hour day
        </p>
        <p class="text-[11px] text-gray-500 mt-2">
            Approved overtime the company has not paid yet. Once it is paid with your salary
            it can no longer be taken as time off.
        </p>
    </div>

    @unless ($showForm)
        <button wire:click="openForm" @disabled($available <= 0)
                class="w-full min-h-[3rem] rounded-control bg-brand-600 text-white font-semibold text-sm
                       active:bg-brand-700 disabled:bg-gray-300 disabled:text-gray-500">
            {{ $available > 0 ? 'Apply for time off' : 'No overtime available' }}
        </button>
    @endunless

    @if ($showForm)
        <div class="card p-3 space-y-3">
            <h3 class="text-sm font-semibold text-gray-800">Apply for time off</h3>

            <div>
                <label class="text-xs font-semibold text-gray-600">Date</label>
                <input type="date" wire:model="f_date"
                       class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300" />
                <x-input-error :messages="$errors->get('f_date')" class="mt-1" />
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600">Hours</label>
                <input type="number" step="0.25" min="0.25" max="24" wire:model.live="f_hours"
                       class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300" />
                @if ((float) $f_hours > 0 && $dayHours > 0)
                    <p class="mt-1 text-[11px] text-gray-500">
                        That is {{ rtrim(rtrim(number_format((float) $f_hours / $dayHours, 2), '0'), '.') }} day(s)
                        of your {{ rtrim(rtrim(number_format($dayHours, 2), '0'), '.') }}-hour day.
                    </p>
                @endif
                <x-input-error :messages="$errors->get('f_hours')" class="mt-1" />
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
        <h3 class="text-sm font-semibold text-gray-800 mb-2">My time off</h3>

        @forelse ($requests as $req)
            <div wire:key="stor-{{ $req->id }}" class="py-2.5 border-t border-gray-100 first:border-t-0">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800">
                            {{ $req->off_date->format('d M Y') }}
                            <span class="text-gray-500 font-normal">
                                · {{ rtrim(rtrim(number_format((float) $req->hours, 2), '0'), '.') }} hour(s)
                            </span>
                        </p>
                        @if ($req->reason)
                            <p class="text-[11px] text-gray-500 mt-0.5">{{ $req->reason }}</p>
                        @endif
                        @foreach ($req->allocations as $alloc)
                            <p class="text-[11px] text-gray-500">
                                {{ rtrim(rtrim(number_format((float) $alloc->hours, 2), '0'), '.') }}h from overtime on
                                {{ $alloc->claim?->claim_date?->format('d M Y') ?? 'an earlier claim' }}
                            </p>
                        @endforeach
                        @if ($req->decision_note)
                            <p class="text-[11px] text-gray-500 mt-0.5 italic">{{ $req->decision_note }}</p>
                        @endif
                    </div>
                    <div class="text-right shrink-0">
                        @php
                            $tone = match ($req->status) {
                                \App\Models\TimeOffRequest::APPROVED  => 'bg-success-100 text-success-700',
                                \App\Models\TimeOffRequest::REJECTED  => 'bg-danger-100 text-danger-700',
                                \App\Models\TimeOffRequest::CANCELLED => 'bg-gray-100 text-gray-600',
                                default                               => 'bg-warning-100 text-warning-700',
                            };
                        @endphp
                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium {{ $tone }}">
                            {{ $req->statusLabel() }}
                        </span>
                        @if (in_array($req->status, ['pending', 'approved'], true))
                            <button wire:click="cancel({{ $req->id }})"
                                    wire:confirm="Cancel this time off? The hours go back on your overtime."
                                    class="block mt-1.5 text-[11px] font-medium text-gray-600 active:text-gray-900">
                                Cancel
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="py-4 text-center text-sm text-gray-500">You have not taken any time off yet.</p>
        @endforelse
    </div>

    {{-- Where the balance comes from. Answers "which overtime" without
         anyone having to ask a manager. --}}
    @if ($claims->isNotEmpty())
        <div class="card p-3">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">Overtime behind this balance</h3>
            <p class="text-[11px] text-gray-500 mb-2">Approved, not yet paid. Oldest is used first.</p>
            @foreach ($claims as $claim)
                <div class="py-1.5 flex items-center justify-between gap-3 border-t border-gray-100 first:border-t-0">
                    <span class="text-xs text-gray-700">{{ $claim->claim_date->format('d M Y') }}</span>
                    <span class="text-xs tabular-nums text-gray-800">
                        {{ rtrim(rtrim(number_format($claim->hoursAvailableForTimeOff(), 2), '0'), '.') }}h left
                        @if ((float) $claim->hours_taken_off > 0)
                            <span class="text-gray-500">
                                (of {{ rtrim(rtrim(number_format((float) $claim->total_ot_hours, 2), '0'), '.') }}h)
                            </span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
