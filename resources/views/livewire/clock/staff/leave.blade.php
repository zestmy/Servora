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
                            @if (($row['expired'] ?? 0) > 0)
                                {{-- Shown, not netted off in silence: a day lost to the
                                     claim window is the one people come and ask about. --}}
                                · <span class="text-warning-700">{{ rtrim(rtrim(number_format($row['expired'], 1), '0'), '.') }} expired</span>
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

    {{-- The public holidays statement.
         The balance above says "3 left"; this says WHICH three, when each
         expires, and which ones were already taken. Staff read a number as a
         claim and a list as a fact — and the ones that expired have to appear
         here or the count above looks like a mistake. --}}
    @if ($credits->isNotEmpty())
        <div class="card p-3">
            <h2 class="text-sm font-semibold text-gray-800 mb-2">My public holidays — {{ $year }}</h2>
            <div class="divide-y divide-gray-100">
                @foreach ($credits as $credit)
                    <div class="py-2 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-800 truncate">{{ $credit['holiday']->name }}</p>
                            <p class="text-[11px] text-gray-500">
                                {{ $credit['holiday']->date->format('j M Y') }}
                                @if ($credit['status'] === \App\Services\Hr\ReplacementHolidays::SPENT && $credit['taken_on'])
                                    · taken {{ $credit['taken_on']->format('j M Y') }}
                                @elseif ($credit['expires_on'])
                                    · take by {{ $credit['expires_on']->format('j M Y') }}
                                @else
                                    · no deadline
                                @endif
                            </p>
                        </div>
                        @php
                            [$label, $tone] = match ($credit['status']) {
                                \App\Services\Hr\ReplacementHolidays::SPENT    => ['Taken',    'bg-gray-100 text-gray-600'],
                                \App\Services\Hr\ReplacementHolidays::EXPIRED  => ['Expired',  'bg-warning-100 text-warning-800'],
                                \App\Services\Hr\ReplacementHolidays::UPCOMING => ['Upcoming', 'bg-info-100 text-info-800'],
                                default                                        => ['Available','bg-success-100 text-success-800'],
                            };
                        @endphp
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $tone }}">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

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

            {{-- A replacement is taken against ONE named holiday, so the form
                 asks which. Without it "1 day RPH" is a number nobody can
                 reconcile against the holidays they actually worked. --}}
            @if ($isReplacement)
                <div>
                    <label class="text-xs font-semibold text-gray-600">Which public holiday?</label>
                    @if ($claimable->isEmpty())
                        <p class="mt-1 rounded-surface bg-warning-50 border border-warning-200 px-3 py-2 text-[11px] text-warning-800">
                            You have no unused replacement days right now.
                        </p>
                    @else
                        <select wire:model.live="f_holiday"
                                class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300">
                            <option value="">— Select —</option>
                            @foreach ($claimable as $credit)
                                <option value="{{ $credit['holiday']->id }}">
                                    {{ $credit['holiday']->name }} — {{ $credit['holiday']->date->format('j M Y') }}
                                </option>
                            @endforeach
                        </select>
                        @php $picked = $f_holiday !== '' ? $claimable->firstWhere('holiday.id', (int) $f_holiday) : null; @endphp
                        @if ($picked && $picked['expires_on'])
                            <p class="mt-1 text-[11px] text-gray-600">
                                Take it by {{ $picked['expires_on']->format('j M Y') }}.
                            </p>
                        @endif
                    @endif
                    <x-input-error :messages="$errors->get('f_holiday')" class="mt-1" />
                </div>
            @endif

            <div class="grid grid-cols-2 gap-2">
                <div class="{{ $isReplacement ? 'col-span-2' : '' }}">
                    <label class="text-xs font-semibold text-gray-600">{{ $isReplacement ? 'Day off' : 'From' }}</label>
                    <input type="date" wire:model.live="f_start"
                           class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300" />
                    @if ($isReplacement)
                        <p class="mt-1 text-[11px] text-gray-500">On or after the holiday, before it expires.</p>
                    @endif
                    <x-input-error :messages="$errors->get('f_start')" class="mt-1" />
                </div>
                @unless ($isReplacement)
                    <div>
                        <label class="text-xs font-semibold text-gray-600">To</label>
                        <input type="date" wire:model.live="f_end" @disabled($f_half)
                               class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300 disabled:bg-gray-100" />
                        <x-input-error :messages="$errors->get('f_end')" class="mt-1" />
                    </div>
                @endunless
            </div>

            @unless ($isReplacement)
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
            @else
                <p class="text-[11px] text-gray-500">One public holiday, one day back.</p>
                <x-input-error :messages="$errors->get('f_days')" class="mt-1" />
            @endunless

            <div>
                <label class="text-xs font-semibold text-gray-600">Reason</label>
                <input type="text" maxlength="500" wire:model="f_reason"
                       class="mt-1 w-full min-h-[2.75rem] text-sm rounded-control border-gray-300" />
                <x-input-error :messages="$errors->get('f_reason')" class="mt-1" />
            </div>

            {{-- The MC. Shown only for the types that ask for one, which is a
                 per-company setting rather than a hardcoded "if it is called
                 sick leave" — see LeaveType::requires_attachment.

                 capture="environment" opens the camera straight onto the slip
                 on a phone, which is what this screen is: the certificate is
                 in somebody's hand and the phone is already out. --}}
            @if ($this->chosenType()?->requires_attachment)
                <div>
                    <label class="text-xs font-semibold text-gray-600">
                        Medical certificate
                        <span class="font-normal text-gray-500">(optional)</span>
                    </label>
                    <input type="file" wire:model="f_attachment" capture="environment"
                           accept="{{ \App\Services\Hr\LeaveAttachment::accepted() }}"
                           class="mt-1 w-full text-sm text-gray-700 file:mr-3 file:min-h-[2.5rem] file:rounded-control
                                  file:border-0 file:bg-brand-50 file:px-3 file:text-sm file:font-medium file:text-brand-700" />

                    <div wire:loading wire:target="f_attachment" class="mt-1 text-[11px] text-gray-500">
                        Uploading…
                    </div>

                    @if ($f_attachment)
                        <p class="mt-1 text-[11px] text-success-700">Attached — it will be sent with your application.</p>
                    @else
                        <p class="mt-1 text-[11px] text-gray-500">
                            Photograph the slip, or attach a PDF. You can add it later if you do not have it yet.
                        </p>
                    @endif

                    <x-input-error :messages="$errors->get('f_attachment')" class="mt-1" />
                </div>
            @endif

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
                        @if ($req->publicHoliday)
                            <p class="text-[11px] text-gray-600">
                                for {{ $req->publicHoliday->name }}, {{ $req->publicHoliday->date->format('j M') }}
                            </p>
                        @endif
                        @if ($req->reason)
                            <p class="text-[11px] text-gray-500 mt-0.5">{{ $req->reason }}</p>
                        @endif
                        @if ($req->decision_note)
                            <p class="text-[11px] text-gray-500 mt-0.5 italic">{{ $req->decision_note }}</p>
                        @endif

                        {{-- The certificate: seen if it is there, added if it is
                             not. The form promises it can follow, so it must. --}}
                        @if ($req->attachment_path)
                            <a href="{{ route('clock.staff.leave.attachment', $req->id) }}" target="_blank" rel="noopener"
                               class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-brand-700">
                                <x-icon name="document" size="h-3 w-3" />
                                Medical certificate
                            </a>
                        @elseif ($req->leaveType?->requires_attachment && $req->status !== \App\Models\LeaveRequest::CANCELLED)
                            @if ($lateFor === $req->id)
                                <div class="mt-1.5">
                                    <input type="file" wire:model="lateFile" capture="environment"
                                           accept="{{ \App\Services\Hr\LeaveAttachment::accepted() }}"
                                           class="w-full text-[11px] text-gray-700 file:mr-2 file:min-h-[2.25rem] file:rounded-control
                                                  file:border-0 file:bg-brand-50 file:px-2 file:text-[11px] file:font-medium file:text-brand-700" />
                                    <x-input-error :messages="$errors->get('lateFile')" class="mt-1" />
                                    <div class="mt-1.5 flex gap-2">
                                        <button wire:click="saveLateAttachment" wire:loading.attr="disabled" wire:target="lateFile,saveLateAttachment"
                                                class="min-h-[2.25rem] flex-1 rounded-control bg-brand-600 px-2 text-[11px] font-medium text-white active:bg-brand-700 disabled:opacity-50">
                                            <span wire:loading.remove wire:target="lateFile,saveLateAttachment">Attach</span>
                                            <span wire:loading wire:target="lateFile,saveLateAttachment">Uploading…</span>
                                        </button>
                                        <button wire:click="$set('lateFor', null)"
                                                class="min-h-[2.25rem] rounded-control border border-gray-300 px-2 text-[11px] font-medium text-gray-700">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            @else
                                <button wire:click="attachTo({{ $req->id }})"
                                        class="mt-1 text-[11px] font-medium text-brand-700 active:text-brand-800">
                                    + Attach medical certificate
                                </button>
                            @endif
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
