@php use App\Models\ClockEvent; @endphp

<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-600">HR / Clock-Ins</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1 flex items-center gap-2">
                Clock-Ins
                @if ($pending > 0)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-warning-100 text-warning-700">
                        {{ number_format($pending) }} to review
                    </span>
                @endif
            </h2>
        </div>
        @can('hr.clock.manage')
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('hr.clock-devices') }}" wire:navigate class="btn-secondary">Kiosks</a>
                <a href="{{ route('hr.face-enrolment') }}" wire:navigate class="btn-secondary">Face Enrolment</a>
                @canDo('settings.hr')
                <a href="{{ route('hr.clock-settings') }}" wire:navigate class="btn-secondary">Settings</a>
                @endcanDo
            </div>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="panel p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                <input type="text" wire:model.live.debounce.400ms="search"
                       placeholder="Name or staff ID"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Outlet</label>
                <select wire:model.live="outletFilter" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select wire:model.live="statusFilter" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="{{ ClockEvent::STATUS_FLAGGED }}">Needs review</option>
                    <option value="">All</option>
                    <option value="{{ ClockEvent::STATUS_VERIFIED }}">Verified</option>
                    <option value="{{ ClockEvent::STATUS_APPROVED }}">Approved</option>
                    <option value="{{ ClockEvent::STATUS_REJECTED }}">Rejected</option>
                </select>
            </div>

            {{-- Where the punch came from.
                 The useful question at a kiosk outlet is who is still using
                 their phone, and most of those punches are perfectly in order
                 — a granted exception never gets flagged — so the review queue
                 will never surface them. This will. --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Punched on</label>
                <select wire:model.live="sourceFilter" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Anywhere</option>
                    @foreach (ClockEvent::SOURCE_LABELS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Deliberately its own control rather than another entry in the
                 Status list. Deleted is orthogonal to status — a deleted punch
                 still has one — and folding them together would make "show me
                 the flagged punch I deleted by mistake" unaskable, which is
                 the only question this filter exists to answer.

                 Only company admins see it, because they are the only ones
                 who can act on what it reveals. --}}
            @if ($this->canDelete())
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Deleted</label>
                    <select wire:model.live="deletedFilter" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Hidden</option>
                        <option value="include">Include deleted</option>
                        <option value="only">Deleted only</option>
                    </select>
                </div>
            @endif
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
                    <input type="date" wire:model.live="from" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                    <input type="date" wire:model.live="to" class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="panel overflow-x-auto">
        <table class="table-surface">
            <thead>
                <tr>
                    <th class="px-3 py-2 text-left">Date</th>
                    <th class="px-3 py-2 text-left">Employee</th>
                    <th class="px-2 py-2 text-left">Type</th>
                    <th class="px-2 py-2 text-right">Time</th>
                    <th class="px-2 py-2 text-right">Rostered</th>
                    <th class="px-2 py-2 text-right">Late</th>
                    @if ($this->canViewPay())
                        <th class="px-2 py-2 text-right">Charge (RM)</th>
                    @endif
                    <th class="px-2 py-2 text-center">Checks</th>
                    <th class="px-2 py-2 text-left">Status</th>
                    <th class="px-2 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    @php
                        $shiftStart = $event->rosterEntry && $event->rosterEntry->shift_start
                            ? \Carbon\Carbon::parse((string) $event->rosterEntry->shift_start)
                            : null;
                    @endphp
                    <tr wire:key="ev-{{ $event->id }}"
                        class="hover:bg-gray-50/70 {{ $event->isRejected() || $event->trashed() ? 'opacity-60' : '' }}">
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $event->work_date->format('d M') }}</td>
                        <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap">
                            {{-- Struck through, not merely faded. Once deleted
                                 rows are mixed in with live ones, opacity
                                 alone is a difference somebody scanning the
                                 list at speed will miss. --}}
                            <span class="{{ $event->trashed() ? 'line-through' : '' }}">
                                {{ $event->employee?->name ?? '—' }}
                            </span>
                            @if ($event->trashed())
                                <span class="ml-1 align-middle rounded bg-gray-200 px-1 text-[10px] font-semibold uppercase tracking-wide text-gray-700">
                                    Deleted
                                </span>
                            @endif
                            <span class="block text-[11px] font-normal text-gray-500">{{ $event->outlet?->name }}</span>
                        </td>
                        <td class="px-2 py-2 text-gray-600 whitespace-nowrap">
                            {{ $event->typeLabel() }}
                            {{-- Under the type rather than in a column of its
                                 own: this table already carries ten, and where
                                 a punch was made is a caption on what it was,
                                 not an independent fact to scan down. --}}
                            <span class="block text-[11px] text-gray-500">{{ $event->sourceDetail() }}</span>
                        </td>
                        <td class="px-2 py-2 text-right tabular-nums text-gray-800">{{ $event->happened_at->format('g:i A') }}</td>
                        <td class="px-2 py-2 text-right tabular-nums text-gray-500">
                            {{ $shiftStart ? $shiftStart->format('g:i A') : '—' }}
                        </td>
                        <td class="px-2 py-2 text-right tabular-nums {{ $event->minutes_late > 0 ? 'text-danger-600 font-semibold' : 'text-gray-500' }}">
                            {{ $event->minutes_late > 0 ? $event->minutes_late . 'm' : '—' }}
                        </td>
                        @if ($this->canViewPay())
                            {{-- A waived charge is struck through rather than blanked.
                                 Showing "—" would say no charge was ever incurred, which
                                 is the opposite of what happened and hides the decision
                                 from the one column a manager scans for money. --}}
                            <td class="px-2 py-2 text-right tabular-nums {{ $event->chargeableAmount() > 0 ? 'text-danger-600' : 'text-gray-500' }}">
                                @if ($event->latenessWaived() && (float) $event->penalty_amount > 0)
                                    <span class="line-through text-gray-400">{{ number_format((float) $event->penalty_amount, 2) }}</span>
                                    <span class="block text-[10px] font-normal text-success-700">waived</span>
                                @else
                                    {{ (float) $event->penalty_amount > 0 ? number_format((float) $event->penalty_amount, 2) : '—' }}
                                    @if ($event->override_late_minutes !== null)
                                        <span class="block text-[10px] font-normal text-gray-500">adjusted</span>
                                    @endif
                                @endif
                            </td>
                        @endif
                        <td class="px-2 py-2 text-center whitespace-nowrap">
                            {{-- Two small marks rather than a sentence: this
                                 column is scanned down, not read across. --}}
                            {{-- The dot stays; the tooltip names the place, so the
                                 detail is a hover away without breaking the scan. --}}
                            <span title="{{ $event->locationLabel() ?? ($event->within_geofence ? 'On site' : 'Not confirmed on site') }}"
                                  class="inline-block w-2.5 h-2.5 rounded-full mr-1 {{ $event->within_geofence ? 'bg-success-500' : 'bg-gray-300' }}"></span>
                            <span title="{{ $event->face_verified ? 'Face matched' : 'Face not matched' }}"
                                  class="inline-block w-2.5 h-2.5 rounded-full {{ $event->face_verified ? 'bg-success-500' : 'bg-gray-300' }}"></span>
                        </td>
                        <td class="px-2 py-2 whitespace-nowrap">
                            @php
                                // Written out in full rather than interpolated:
                                // Tailwind scans source text for class names, so
                                // a composed "bg-{$c}-100" is never generated.
                                $chip = match ($event->status) {
                                    ClockEvent::STATUS_VERIFIED => 'bg-success-100 text-success-700',
                                    ClockEvent::STATUS_APPROVED => 'bg-brand-100 text-brand-700',
                                    ClockEvent::STATUS_REJECTED => 'bg-gray-100 text-gray-600',
                                    default                     => 'bg-warning-100 text-warning-700',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $chip }}">{{ $event->statusLabel() }}</span>
                        </td>
                        <td class="px-2 py-2 text-right whitespace-nowrap">
                            <button wire:click="view({{ $event->id }})"
                                    class="text-xs font-medium text-brand-700 hover:underline">Review</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-600">
                            Nothing here for this filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $events->links() }}</div>

    {{-- Review panel --}}
    @if ($viewing)
        {{-- The OVERLAY scrolls, not the panel, and there is no vh anywhere.
             A max-h-[92vh] panel is taller than what iOS actually shows once
             the browser chrome is counted, so its lower half — including
             Approve and Reject — sat below the fold with no way to reach it. --}}
        {{-- Teleported to the body, like every other modal here. `main` is
             `flex-1 overflow-y-auto` inside an `h-screen overflow-hidden` shell, and a
             fixed overlay rendered inside it got clipped by that scroll container —
             the panel was cut off mid-content with Approve and Reject unreachable, and
             the page's own toolbar drew on top of the dimmed backdrop. --}}
        @teleport('body')
        <div class="fixed inset-0 z-[100] bg-gray-900/50 overflow-y-auto"
             wire:key="panel-{{ $viewing->id }}">
            <div class="min-h-full flex items-end sm:items-center justify-center p-0 sm:p-4">
            <div class="bg-white w-full sm:max-w-2xl sm:rounded-xl shadow-xl">
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 sticky top-0 bg-white">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">{{ $viewing->employee?->name }}</h3>
                        <p class="text-xs text-gray-600">
                            {{ $viewing->work_date->format('D d M Y') }} ·
                            {{ $viewing->typeLabel() }} at {{ $viewing->happened_at->format('g:i A') }}
                        </p>
                    </div>
                    <button wire:click="closePanel" class="text-gray-400 hover:text-gray-600 text-2xl leading-none px-2" aria-label="Close">&times;</button>
                </div>

                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        @if ($viewing->selfie_path)
                            {{-- Not lazy-loaded and not cached beyond the panel:
                                 this is a photo of a member of staff, and it
                                 should exist on screen only while it is being
                                 looked at. --}}
                            <img src="{{ route('hr.clock-ins.selfie', $viewing) }}"
                                 alt="Selfie taken at the punch"
                                 class="w-full rounded-lg border border-gray-200 bg-gray-50"
                                 onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden')">
                            {{-- A broken image is an empty rectangle that says
                                 nothing. This says which of the two it was. --}}
                            <div class="hidden w-full aspect-[4/3] rounded-lg border border-dashed border-danger-300 grid place-items-center px-4 text-center text-sm text-danger-700">
                                The photo could not be loaded — it may have been swept from storage.
                            </div>
                        @else
                            <div class="w-full aspect-[4/3] rounded-lg border border-dashed border-gray-300 grid place-items-center text-sm text-gray-500">
                                No photo captured
                            </div>
                        @endif
                    </div>

                    <div class="space-y-3 text-sm">
                        @if ($viewing->flagLabels())
                            <div class="rounded-lg bg-warning-50 border border-warning-200 px-3 py-2">
                                <ul class="space-y-0.5 text-warning-800">
                                    @foreach ($viewing->flagLabels() as $label)
                                        <li>• {{ $label }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <dl class="space-y-1.5">
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-600">Distance from outlet</dt>
                                <dd class="text-gray-900 tabular-nums">
                                    {{ $viewing->distance_m !== null ? number_format($viewing->distance_m) . ' m' : 'unknown' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-600">GPS accuracy</dt>
                                <dd class="text-gray-900 tabular-nums">
                                    {{ $viewing->accuracy_m !== null ? '±' . number_format($viewing->accuracy_m) . ' m' : 'unknown' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-600">Face match</dt>
                                <dd class="text-gray-900 tabular-nums">
                                    {{-- The raw distance, not a percentage. It
                                         is the number the threshold is set in,
                                         so a manager tuning one can compare
                                         them directly. Lower is a better match. --}}
                                    {{ $viewing->face_distance !== null ? number_format((float) $viewing->face_distance, 3) : 'not checked' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-600">Late by</dt>
                                <dd class="text-gray-900 tabular-nums">{{ $viewing->minutes_late }} min</dd>
                            </div>
                        </dl>

                        @if ($viewing->latitude)
                            {{-- The place, then the link. A manager reading a flagged
                                 punch wants "where was this" answered before deciding
                                 whether to open a map at all. --}}
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-start gap-x-2 gap-y-1">
                                    <span class="inline-flex items-start gap-1.5 text-xs font-medium
                                                 {{ $viewing->within_geofence ? 'text-success-700' : 'text-warning-700' }}">
                                        <svg class="h-3.5 w-3.5 flex-shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span>{{ $viewing->locationLabel() }}</span>
                                    </span>
                                    <span class="text-gray-300">·</span>
                                    <a href="https://www.google.com/maps?q={{ $viewing->latitude }},{{ $viewing->longitude }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="text-xs font-medium text-brand-700 hover:underline whitespace-nowrap">
                                        Open on a map
                                    </a>
                                </div>
                                {{-- A street address does not answer "was this person at
                                     work", so the outlet-relative line stays underneath
                                     it whenever both exist. --}}
                                @if (filled($viewing->address) && $viewing->geofenceLabel())
                                    <p class="text-[11px] text-gray-500 pl-5">{{ $viewing->geofenceLabel() }}</p>
                                @endif
                            </div>
                        @elseif ($viewing->locationLabel())
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500">
                                <svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636L5.636 18.364m12.728 0L5.636 5.636"/>
                                </svg>
                                {{ $viewing->locationLabel() }}
                            </span>
                        @endif

                        @if ($viewing->reason)
                            <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                                <p class="text-xs text-gray-600 mb-0.5">Reason given</p>
                                <p class="text-gray-800">{{ $viewing->reason }}</p>
                            </div>
                        @endif

                        @if ($viewing->reviewed_at)
                            <p class="text-xs text-gray-500">
                                Reviewed by {{ $viewing->reviewer?->name ?? 'someone since removed' }}
                                on {{ $viewing->reviewed_at->format('d M Y, g:i A') }}
                            </p>
                        @endif
                    </div>
                </div>

                <div class="px-5 pb-5 space-y-3">
                    {{-- A deleted punch shows one action and nothing else: an
                         override box and a note field are controls with
                         nothing left to act on. --}}
                    @if (! $viewing->trashed())
                    @if ($this->canViewPay())
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Charge for (minutes) — leave blank to keep the computed {{ $viewing->chargeable_late_minutes }}
                            </label>
                            <input type="number" min="0" wire:model="overrideMinutes"
                                   class="w-40 rounded-lg border-gray-300 text-sm">
                            @error('overrideMinutes') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Note (the employee sees this if you reject)</label>
                        <textarea wire:model="reviewNote" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                    </div>

                    {{-- ── Waiving the late charge ──────────────────────────
                         Its own block, below the review controls and visually
                         separated from them, because it is not part of the
                         approve/reject decision. A punch can be perfectly
                         verified and still carry a charge somebody decides not
                         to collect — and the reverse: approving a flagged punch
                         confirms it happened, which is the opposite of
                         forgiving what it cost. --}}
                    @if ($this->canWaiveLateness() && $viewing->hasLatenessCharge())
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                            @if ($viewing->latenessWaived())
                                <p class="text-sm font-medium text-success-800">
                                    @if ($this->canViewPay())
                                        RM{{ number_format((float) $viewing->penalty_amount, 2) }} late charge waived
                                    @else
                                        Late charge waived
                                    @endif
                                </p>
                                <p class="mt-0.5 text-xs text-gray-600">
                                    by {{ $viewing->latenessWaiver?->name ?? 'someone since removed' }}
                                    @if ($viewing->lateness_waived_at)
                                        on {{ $viewing->lateness_waived_at->format('d M Y, g:i A') }}
                                    @endif
                                </p>
                                @if (filled($viewing->lateness_waive_reason))
                                    <p class="mt-2 text-sm text-gray-800">{{ $viewing->lateness_waive_reason }}</p>
                                @endif

                                {{-- The punch still says how late it was, and still
                                     says what that would have cost. Only the
                                     collection changed. --}}
                                <button type="button"
                                        wire:click="restoreLatenessCharge({{ $viewing->id }})"
                                        wire:confirm="Charge this late arrival again?"
                                        class="btn-secondary mt-3">
                                    Apply the charge again
                                </button>
                            @else
                                <label class="block text-xs font-medium text-gray-600 mb-1">
                                    @if ($this->canViewPay())
                                        Waive the RM{{ number_format((float) $viewing->penalty_amount, 2) }} late charge — say why
                                    @else
                                        Waive the late charge — say why
                                    @endif
                                </label>
                                <textarea wire:model="waiveReason" rows="2"
                                          placeholder="e.g. Roster changed at short notice; told to come in at 10."
                                          class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                                @error('waiveReason')
                                    <p class="text-xs text-danger-600 mt-1">{{ $message }}</p>
                                @enderror

                                <p class="mt-1 text-[11px] text-gray-500">
                                    The punch keeps its {{ $viewing->minutes_late }} minutes and the amount on record —
                                    the charge simply stops being deducted from the service charge.
                                </p>

                                <button type="button"
                                        wire:click="waiveLateness({{ $viewing->id }})"
                                        class="btn-secondary mt-3">
                                    Waive the charge
                                </button>
                            @endif
                        </div>
                    @endif

                    @endif

                    @if ($viewing->trashed())
                        {{-- A deleted punch gets one action, not four.
                             Approve and Reject would be writing decisions onto
                             a record that counts for nothing, and the server
                             refuses them anyway — offering the buttons would
                             just be a way to find that out the hard way. --}}
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <p class="text-sm font-medium text-gray-900">This punch is deleted.</p>
                            <p class="mt-0.5 text-xs text-gray-600">
                                It counts for nothing — not attendance, not the service charge
                                @if ($viewing->deleter)
                                    — deleted by {{ $viewing->deleter->name }}
                                @endif
                                @if ($viewing->deleted_at)
                                    on {{ $viewing->deleted_at->format('d M Y, g:i A') }}
                                @endif.
                            </p>

                            @if ($this->canDelete())
                                <button wire:click="restoreEvent({{ $viewing->id }})"
                                        wire:confirm="Restore this punch?{{ $this->canViewPay() && (float) $viewing->penalty_amount > 0
                                            ? ' Its RM' . number_format((float) $viewing->penalty_amount, 2) . ' late charge will apply again.'
                                            : '' }}"
                                        class="btn-primary mt-3">
                                    Restore
                                </button>
                            @endif
                        </div>
                    @else
                    <div class="flex flex-wrap items-center gap-2 justify-end">
                        {{-- Delete sits apart from Approve and Reject, on the
                             far side of the row.

                             Rejecting voids a punch and keeps it on the
                             screen; deleting takes it off the screen
                             altogether. They are different decisions and the
                             destructive one should not be a neighbour of the
                             button somebody presses forty times a morning.

                             Company admin only — an outlet manager can
                             approve and reject all day and still not make a
                             record disappear. --}}
                        @if ($this->canDelete())
                            @php
                                $charge = $this->canViewPay() ? (float) $viewing->penalty_amount : 0.0;
                            @endphp
                            <button wire:click="deleteEvent({{ $viewing->id }})"
                                    data-confirm-delete="Delete this punch?{{ $charge > 0
                                        ? ' It carries an RM' . number_format($charge, 2) . ' late charge, which will stop applying to the service charge.'
                                        : '' }} It stops counting everywhere. You can bring it back with the Deleted filter."
                                    class="mr-auto px-3 py-2 text-sm font-medium text-danger-700 hover:underline">
                                Delete
                            </button>
                        @endif

                        <button wire:click="reject({{ $viewing->id }})"
                                wire:confirm="Reject this punch? It stops counting for attendance and for the service charge."
                                class="px-3 py-2 text-sm font-medium text-danger-700 border border-danger-200 rounded-lg hover:bg-danger-50">
                            Reject
                        </button>
                        <button wire:click="approve({{ $viewing->id }})" class="btn-primary">Approve</button>
                    </div>
                    @endif
                </div>
            </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
