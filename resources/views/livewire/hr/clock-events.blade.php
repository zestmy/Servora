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
                <a href="{{ route('hr.face-enrolment') }}" wire:navigate class="btn-secondary">Face Enrolment</a>
                <a href="{{ route('hr.clock-settings') }}" wire:navigate class="btn-secondary">Settings</a>
            </div>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="panel p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
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
                    <tr wire:key="ev-{{ $event->id }}" class="hover:bg-gray-50/70 {{ $event->isRejected() ? 'opacity-60' : '' }}">
                        <td class="px-3 py-2 whitespace-nowrap text-gray-700">{{ $event->work_date->format('d M') }}</td>
                        <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap">
                            {{ $event->employee?->name ?? '—' }}
                            <span class="block text-[11px] font-normal text-gray-500">{{ $event->outlet?->name }}</span>
                        </td>
                        <td class="px-2 py-2 text-gray-600 whitespace-nowrap">{{ $event->typeLabel() }}</td>
                        <td class="px-2 py-2 text-right tabular-nums text-gray-800">{{ $event->happened_at->format('g:i A') }}</td>
                        <td class="px-2 py-2 text-right tabular-nums text-gray-500">
                            {{ $shiftStart ? $shiftStart->format('g:i A') : '—' }}
                        </td>
                        <td class="px-2 py-2 text-right tabular-nums {{ $event->minutes_late > 0 ? 'text-danger-600 font-semibold' : 'text-gray-500' }}">
                            {{ $event->minutes_late > 0 ? $event->minutes_late . 'm' : '—' }}
                        </td>
                        @if ($this->canViewPay())
                            <td class="px-2 py-2 text-right tabular-nums {{ (float) $event->penalty_amount > 0 ? 'text-danger-600' : 'text-gray-500' }}">
                                {{ (float) $event->penalty_amount > 0 ? number_format((float) $event->penalty_amount, 2) : '—' }}
                                @if ($event->override_late_minutes !== null)
                                    <span class="block text-[10px] font-normal text-gray-500">adjusted</span>
                                @endif
                            </td>
                        @endif
                        <td class="px-2 py-2 text-center whitespace-nowrap">
                            {{-- Two small marks rather than a sentence: this
                                 column is scanned down, not read across. --}}
                            <span title="{{ $event->within_geofence ? 'On site' : 'Not confirmed on site' }}"
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
        <div class="fixed inset-0 z-40 bg-gray-900/50 overflow-y-auto"
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
                            <a href="https://www.google.com/maps?q={{ $viewing->latitude }},{{ $viewing->longitude }}"
                               target="_blank" rel="noopener noreferrer"
                               class="inline-block text-xs font-medium text-brand-700 hover:underline">
                                Open on a map
                            </a>
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

                    <div class="flex flex-wrap gap-2 justify-end">
                        <button wire:click="reject({{ $viewing->id }})"
                                wire:confirm="Reject this punch? It stops counting for attendance and for the service charge."
                                class="px-3 py-2 text-sm font-medium text-danger-700 border border-danger-200 rounded-lg hover:bg-danger-50">
                            Reject
                        </button>
                        <button wire:click="approve({{ $viewing->id }})" class="btn-primary">Approve</button>
                    </div>
                </div>
            </div>
            </div>
        </div>
    @endif
</div>
