<div class="space-y-3">
    <div class="card p-3">
        <h2 class="text-sm font-semibold text-gray-800">My roster</h2>
        <p class="text-[11px] text-gray-500 mt-0.5">
            Approved rosters only. A week still being worked on by your manager is not shown —
            turning up for a shift that was changed an hour later is worse than not knowing yet.
        </p>
    </div>

    @forelse ($weeks as $weekStart => $entries)
        @php $start = \Carbon\Carbon::parse($weekStart); @endphp
        <div class="card p-3">
            <p class="text-xs font-semibold text-gray-700 mb-2">
                {{ $start->format('d M') }} – {{ $start->copy()->endOfWeek()->format('d M Y') }}
            </p>

            <div class="divide-y divide-gray-100">
                @foreach ($entries as $entry)
                    @php
                        $date    = \Carbon\Carbon::parse($entry->day_date);
                        $isToday = $entry->day_date->toDateString() === $today;
                    @endphp
                    <div class="py-2 flex items-start justify-between gap-3 {{ $isToday ? '-mx-3 px-3 bg-brand-50' : '' }}">
                        <div class="min-w-0">
                            <p class="text-sm font-medium {{ $isToday ? 'text-brand-800' : 'text-gray-800' }}">
                                {{ $date->format('D d M') }}
                                @if ($isToday)
                                    <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-brand-700">today</span>
                                @endif
                            </p>
                            @if ($entry->station?->name)
                                <p class="text-[11px] text-gray-500">{{ $entry->station->name }}</p>
                            @endif
                            @if ($entry->notes)
                                <p class="text-[11px] text-gray-500">{{ $entry->notes }}</p>
                            @endif
                        </div>

                        <div class="text-right shrink-0">
                            @if ($entry->is_off_day)
                                {{-- A rest day is information, not an absence of
                                     it — someone checking whether they work
                                     tomorrow needs to see the answer "no". --}}
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">
                                    {{ $entry->leave_type ? ucfirst(str_replace('_', ' ', $entry->leave_type)) : 'Off' }}
                                </span>
                            @elseif ($entry->shift_start)
                                <p class="text-sm font-semibold tabular-nums text-gray-800">
                                    {{ \Carbon\Carbon::parse($entry->shift_start)->format('H:i') }}
                                    –
                                    {{ $entry->shift_end ? \Carbon\Carbon::parse($entry->shift_end)->format('H:i') : '' }}
                                </p>
                                @if ((float) $entry->rest_duration > 0)
                                    <p class="text-[11px] text-gray-500">
                                        {{ rtrim(rtrim(number_format((float) $entry->rest_duration, 2), '0'), '.') }}h break
                                    </p>
                                @endif
                                @if ((float) $entry->planned_ot > 0)
                                    <p class="text-[11px] text-warning-700">
                                        +{{ rtrim(rtrim(number_format((float) $entry->planned_ot, 2), '0'), '.') }}h planned OT
                                    </p>
                                @endif
                            @else
                                <span class="text-[11px] text-gray-400">no times set</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card p-6 text-center">
            <p class="text-sm text-gray-700">
                @if ($hasRoster)
                    You are not on the roster for these weeks.
                @else
                    No approved roster yet for these weeks.
                @endif
            </p>
            <p class="text-[11px] text-gray-500 mt-1">
                Rosters appear here once your manager has approved the week.
            </p>
        </div>
    @endforelse
</div>
