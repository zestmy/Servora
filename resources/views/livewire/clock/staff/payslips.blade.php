<div>
    <h1 class="sr-only">My payslips</h1>

    @forelse ($lines as $line)
        {{-- The whole row is the link.

             A phone target the size of a "Download" chip is a target somebody
             misses with a thumb while walking, and there is nothing else on
             this row to tap — so the row itself is the control rather than
             something containing one. --}}
        <a href="{{ route('clock.staff.payslip', ['line' => $line->id]) }}"
           target="_blank" rel="noopener"
           class="mb-2.5 flex min-h-[4.25rem] items-center gap-3 rounded-xl border border-gray-200
                  bg-white px-4 py-3 active:bg-gray-50">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900">{{ $line->run->periodLabel() }}</p>

                {{-- The dates actually covered, whenever they are not the
                     calendar month. Somebody checking a payslip against their
                     own record of shifts needs the range, and the month a
                     company changes its pay cycle is exactly when they will. --}}
                @if ($line->run->hasCustomRange())
                    <p class="mt-0.5 text-[11px] text-gray-500">{{ $line->run->rangeLabel() }}</p>
                @endif

                {{-- One line, truncated. The reference is what a manager will
                     ask for and the outlet is context; neither is worth
                     wrapping an outlet name like "DOTTY'S - SURIA KLCC" across
                     two rows and pushing the amount out of line. --}}
                <p class="mt-0.5 truncate text-[11px] text-gray-500">
                    {{ $line->run->reference }}
                    @if ($line->outlet_name)
                        &middot; {{ $line->outlet_name }}
                    @endif
                </p>
            </div>

            <div class="shrink-0 text-right">
                {{-- Net, and labelled as net. It is the figure that matches
                     the bank transfer, which is the number somebody opens this
                     screen holding their banking app to check. --}}
                <p class="text-[11px] uppercase tracking-wide text-gray-500">Net pay</p>
                <p class="text-base font-bold tabular-nums text-gray-900">
                    RM {{ number_format((float) $line->net, 2) }}
                </p>
            </div>

            <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    @empty
        <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center">
            <p class="text-sm font-semibold text-gray-900">No payslips yet</p>
            {{-- Says WHY it is empty, not just that it is. "Nothing here" on a
                 pay screen reads as an error, and the first thing somebody
                 does about it is ask a manager whether they have been paid. --}}
            <p class="mt-1 text-xs text-gray-600">
                A payslip appears here once your manager has approved that month's payroll.
                Ask them if you are expecting one.
            </p>
        </div>
    @endforelse

    @if ($lines->isNotEmpty())
        <p class="mt-3 px-1 text-[11px] leading-relaxed text-gray-500">
            Payslips open as a PDF. If a figure looks wrong, check
            <a href="{{ route('clock.staff.history') }}" wire:navigate
               class="font-medium text-brand-700 underline">My punches</a>
            for that period first — it is the record the pay was worked out from.
        </p>
    @endif
</div>
