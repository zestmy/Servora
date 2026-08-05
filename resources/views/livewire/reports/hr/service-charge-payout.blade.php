<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-start gap-3">
            <a href="{{ route('reports.hub') }}" title="Back to Reports"
               class="mt-0.5 p-1.5 rounded-control text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="text-xs text-gray-600">Reports / HR</p>
                <h2 class="text-lg font-semibold text-gray-700 mt-1">Service Charge Payout</h2>
            </div>
        </div>
        @if ($period && $distribution)
            <x-download-link :href="route('hr.attendance.payout-pdf', [
                        'from' => $period->period_from->format('Y-m-d'),
                        'to' => $period->period_to->format('Y-m-d'),
                        'outlet' => $period->outlet_id,
                    ])"
                    title="One payout slip per employee"
                    class="px-3 py-2 text-sm font-medium text-danger-600 border border-danger-200 rounded-lg hover:bg-danger-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                Payout slips
            </x-download-link>
        @endif
    </div>

    @if ($periods->isEmpty())
        <div class="card p-8 text-center">
            <p class="text-sm text-gray-700">No service charge has been saved yet.</p>
            <p class="text-xs text-gray-500 mt-1">
                Pools are entered on <a href="{{ route('hr.attendance') }}" class="text-brand-600 hover:underline">Attendance Record</a>,
                against the exact period they cover.
            </p>
        </div>
    @else
        {{-- Saved pools, not a date picker: a pool exists only for the exact
             from/to it was saved against, so a typed date a day out finds
             nothing and looks like a bug. --}}
        <div class="card p-4 mb-4">
            <p class="text-xs font-medium text-gray-700 mb-2">Period</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($periods as $p)
                    <button wire:click="select({{ $p->id }})"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg border transition text-left
                                   {{ $period && $period->id === $p->id
                                       ? 'bg-brand-600 text-white border-brand-600'
                                       : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                        {{ $p->period_from->format('d M') }} – {{ $p->period_to->format('d M Y') }}
                        <span class="block text-[10px] {{ $period && $period->id === $p->id ? 'text-brand-100' : 'text-gray-500' }}">
                            {{ $p->outlet?->name ?? 'All outlets' }} · RM {{ number_format((float) $p->amount, 2) }}
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($period && $distribution)
        @php $d = $distribution; @endphp

        {{-- The pool, end to end, before the per-person table --}}
        <div class="card p-4 mb-4">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                    Collected RM {{ number_format($d['collected'], 2) }}
                </span>
                @if ($d['retentionPct'] > 0)
                    <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                        − {{ rtrim(rtrim(number_format($d['retentionPct'], 2, '.', ''), '0'), '.') }}%
                        (RM {{ number_format($d['retentionAmt'], 2) }})
                    </span>
                @endif
                <span class="px-2.5 py-1 rounded-full bg-brand-100 text-brand-800 font-semibold">
                    Distributable RM {{ number_format($d['distributable'], 2) }}
                </span>
                <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                    Points {{ number_format($d['totalPoints'], 2) }}
                    @if ($d['fundPoints'] > 0)
                        <span class="text-gray-500">({{ number_format($d['staffPoints'], 2) }} staff
                        + {{ number_format($d['fundPoints'], 2) }} funds)</span>
                    @endif
                </span>
                <span class="px-2.5 py-1 rounded-full bg-gray-100 text-gray-600">
                    RM {{ number_format($d['perPoint']) }} / point
                </span>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table-surface min-w-[900px]">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Employee</th>
                            <th class="px-2 py-2 text-left">Section</th>
                            <th class="px-2 py-2 text-right">Points</th>
                            <th class="px-2 py-2 text-right">Gross</th>
                            <th class="px-2 py-2 text-right">Attendance</th>
                            <th class="px-2 py-2 text-right">
                                Lateness
                                @if ($lateRate > 0)
                                    <span class="block font-normal normal-case text-[10px] text-gray-500">
                                        @ RM {{ rtrim(rtrim(number_format($lateRate, 2, '.', ''), '0'), '.') }}/min
                                    </span>
                                @endif
                            </th>
                            <th class="px-2 py-2 text-right">Special</th>
                            <th class="px-2 py-2 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($d['rows'] as $row)
                            {{-- Someone with no points takes no share; shown greyed
                                 rather than hidden, so "why am I not on this" has an
                                 answer on the page. --}}
                            <tr wire:key="scr-{{ $row['employee']->id }}" class="hover:bg-gray-50 {{ $row['points'] <= 0 ? 'opacity-50' : '' }}">
                                <td class="px-3 py-1.5 font-medium text-gray-800 whitespace-nowrap">
                                    {{ $row['employee']->name }}
                                    @if ($row['employee']->staff_id)
                                        <span class="block text-[10px] text-gray-500 font-mono">{{ $row['employee']->staff_id }}</span>
                                    @endif
                                    {{-- Says why the figure is zero. Without this the row
                                         reads as an entitlement that came out to nothing. --}}
                                    @if ($row['excluded'])
                                        <span class="mt-0.5 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-600">
                                            excluded from this pool
                                        </span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-gray-600 text-sm">{{ $row['employee']->section?->name ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums text-gray-600">{{ $row['points'] > 0 ? number_format($row['points'], 2) : '—' }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums text-gray-700">{{ $row['points'] > 0 ? number_format($row['gross'], 2) : '—' }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums {{ $row['dedAmt'] > 0 ? 'text-danger-600' : 'text-gray-400' }}">
                                    {{ $row['dedAmt'] > 0 ? '-' . number_format($row['dedAmt'], 2) : '—' }}
                                    @if ($row['dedPct'] > 0)
                                        <span class="block text-[10px] text-gray-500">{{ rtrim(rtrim(number_format($row['dedPct'], 2, '.', ''), '0'), '.') }}%</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-right tabular-nums {{ $row['lateAmt'] > 0 ? 'text-danger-600' : 'text-gray-400' }}">
                                    {{ $row['lateAmt'] > 0 ? '-' . number_format($row['lateAmt'], 2) : '—' }}
                                    @if ($row['lateMins'] > 0)
                                        <span class="block text-[10px] text-gray-500">{{ $row['lateMins'] }} min</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-right tabular-nums {{ $row['specialAmt'] > 0 ? 'text-danger-600' : 'text-gray-400' }}">
                                    {{ $row['specialAmt'] > 0 ? '-' . number_format($row['specialAmt'], 2) : '—' }}
                                    @if ($row['specialNote'] !== '')
                                        <span class="block text-[10px] text-gray-500">{{ $row['specialNote'] }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-right font-semibold text-brand-700 tabular-nums">{{ $row['points'] > 0 ? number_format($row['net'], 2) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold">
                        <tr>
                            <td class="px-3 py-2 text-gray-700" colspan="3">Staff total</td>
                            <td class="px-2 py-2 text-right tabular-nums text-gray-700">{{ number_format($d['totals']['gross'], 2) }}</td>
                            <td class="px-2 py-2 text-right tabular-nums text-danger-600">-{{ number_format($d['totals']['deduction'], 2) }}</td>
                            <td class="px-2 py-2 text-right tabular-nums text-danger-600">-{{ number_format($d['totals']['lateAmt'], 2) }}</td>
                            <td class="px-2 py-2 text-right tabular-nums text-danger-600">-{{ number_format($d['totals']['specialAmt'], 2) }}</td>
                            <td class="px-2 py-2 text-right tabular-nums text-brand-700">{{ number_format($d['totals']['net'], 2) }}</td>
                        </tr>
                        @foreach ($d['funds'] as $fund)
                            <tr class="font-normal text-gray-700">
                                <td class="px-3 py-1.5 italic" colspan="3">{{ $fund['name'] }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($fund['amount'], 2) }}</td>
                                <td colspan="3"></td>
                                <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format($fund['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-gray-200">
                            <td class="px-3 py-2 text-gray-700" colspan="7">
                                Allocated of RM {{ number_format($d['distributable'], 2) }} distributable
                            </td>
                            <td class="px-2 py-2 text-right tabular-nums text-brand-700">{{ number_format($d['allocated'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="px-4 py-2 text-[11px] text-gray-600 border-t border-gray-100">
                Every active employee in this outlet scope is included, unfiltered — this answers who was paid,
                not what a filtered grid was showing.
                Gross = points × RM/point (distributable ÷ total points, rounded down to the nearest ringgit);
                the remainder stays undistributed.
                Pools are entered on <a href="{{ route('hr.attendance') }}" class="text-brand-600 hover:underline">Attendance Record</a>.
            </p>
        </div>
    @elseif ($periods->isNotEmpty())
        <div class="card p-8 text-center text-sm text-gray-600">Select a period above.</div>
    @endif
</div>
