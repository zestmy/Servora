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
                                Waiting for your manager{{ $event->flagLabels() ? ': ' . implode(', ', $event->flagLabels()) : '' }}
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
