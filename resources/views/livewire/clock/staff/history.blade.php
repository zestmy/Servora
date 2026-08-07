@php use App\Models\ClockEvent; @endphp

<div>
    <h1 class="sr-only">My punches</h1>

    @forelse ($days as $date => $events)
        @php $day = \Carbon\Carbon::parse($date); @endphp

        <div class="mb-3 rounded-xl border border-gray-200 bg-white overflow-hidden">
            <div class="px-4 py-2 bg-gray-50 border-b border-gray-100">
                <p class="text-sm font-semibold text-gray-800">
                    {{ $day->isToday() ? 'Today' : $day->format('D j M') }}
                </p>
            </div>

            <div class="divide-y divide-gray-100">
                @foreach ($events->sortBy('happened_at') as $event)
                    <div class="px-4 py-2.5 {{ $event->isRejected() ? 'opacity-50' : '' }}">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm text-gray-700">
                                {{ $event->typeLabel() }}
                            </span>
                            <span class="text-sm font-medium text-gray-900 tabular-nums">
                                {{ $event->happened_at->format('g:i A') }}
                            </span>
                        </div>

                        {{-- Where it was recorded.

                             The same sentence the manager's review screen
                             shows, from the same method, and that is the point
                             rather than a convenience: the location is the part
                             of a punch an employee has no other way to check,
                             and it is the part a disagreement is most often
                             about. Somebody told their 4:15 was logged 800m
                             from the outlet should be able to see that on their
                             own phone rather than hear it secondhand a fortnight
                             later.

                             A pin for a location, a tablet for a kiosk — the
                             glyph carries "which door did this come through"
                             without a second line of text. --}}
                        @if ($event->locationLabel())
                            <p class="mt-0.5 flex items-start gap-1 text-xs text-gray-500">
                                <svg class="mt-px h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                                    @if ($event->fromKiosk())
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M9.75 17h4.5m-6.75 4h9a2.25 2.25 0 002.25-2.25V5.25A2.25 2.25 0 0016.5 3h-9a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21z"/>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                                    @endif
                                    </svg>
                                <span>{{ $event->locationLabel() }}</span>
                            </p>
                        @endif

                        @if ($event->minutes_late > 0)
                            <p class="mt-0.5 text-xs text-amber-700">
                                {{ $event->minutes_late }} {{ Str::plural('minute', $event->minutes_late) }} late
                                @if ((float) $event->penalty_amount > 0)
                                    · RM {{ number_format((float) $event->penalty_amount, 2) }} deducted
                                @endif
                                @if ($event->override_late_minutes !== null)
                                    {{-- Shown plainly. A manager who reduced a
                                         charge should get the credit, and one
                                         who raised it should be answerable. --}}
                                    · adjusted by your manager to {{ $event->override_late_minutes }} min
                                @endif
                            </p>
                        @endif

                        @if ($event->isRejected())
                            <p class="mt-0.5 text-xs text-gray-600">
                                Rejected by your manager — this punch does not count.
                                @if ($event->review_note)
                                    “{{ $event->review_note }}”
                                @endif
                            </p>
                        @elseif ($event->needsReview())
                            <p class="mt-0.5 text-xs text-amber-700">
                                {{-- Only the reasons a manager is actually
                                     being asked about. "No rostered shift"
                                     never sent a punch here and is usually
                                     nobody's fault, but listed alongside the
                                     real reason it reads as a second charge. --}}
                                Waiting for your manager{{ $event->reviewFlagLabels() ? ': ' . implode(', ', $event->reviewFlagLabels()) : '' }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-gray-200 bg-white px-4 py-8 text-center">
            <p class="text-sm text-gray-600">Nothing recorded in the last two weeks.</p>
        </div>
    @endforelse
</div>
