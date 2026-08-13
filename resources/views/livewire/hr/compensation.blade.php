<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-600">HR / Compensation</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Compensation</h2>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="previousMonth" class="icon-btn" title="Previous month">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <input type="month" wire:model.live="month" class="text-sm rounded-lg border-gray-300 shadow-sm" />
            <button wire:click="nextMonth" class="icon-btn" title="Next month">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
            <x-download-link :href="route('hr.compensation.export-pdf', ['month' => $month, 'outlet' => $outletFilter, 'section' => $sectionFilter])"
                    title="Export PDF"
                    class="px-2.5 md:px-3 py-2 text-sm font-medium text-danger-600 border border-danger-200 rounded-lg hover:bg-danger-50 transition flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span class="hidden sm:inline">PDF</span>
            </x-download-link>
            <a href="{{ route('settings.pay-components') }}" class="btn-secondary">Pay components</a>
            <a href="{{ route('settings.statutory') }}" class="btn-secondary">Statutory</a>
        </div>
    </div>

    @if ($summary['statutory']->ratesUnconfirmed())
        {{-- Loud on purpose: nobody should file a return off a seeded guess. --}}
        <div class="mb-4 px-4 py-3 bg-warning-50 border border-warning-200 text-warning-800 text-sm rounded-lg">
            <strong>Statutory rates have not been confirmed.</strong>
            EPF, SOCSO, EIS and PCB below are calculated from seeded defaults that nobody has checked yet.
            <a href="{{ route('settings.statutory') }}" class="underline font-medium">Review and confirm them</a>
            before using these figures for payroll or a statutory return.
        </div>
    @endif

    {{-- Salary changes waiting for sign-off --}}
    @if ($pending->isNotEmpty())
        <div class="card overflow-hidden mb-4">
            <div class="px-4 py-3 bg-warning-50 border-b border-warning-200">
                <h3 class="text-sm font-semibold text-warning-700">Salary changes awaiting approval ({{ $pending->count() }})</h3>
                <p class="text-xs text-gray-600">
                    @if ($canApprove) Review each one before it takes effect. @else Someone with approval rights has to sign these off. @endif
                </p>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach ($pending as $rev)
                    <div wire:key="pending-{{ $rev->id }}" class="px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-1">
                        <span class="text-sm font-medium text-gray-800">{{ $rev->employee?->name ?? 'Deleted employee' }}</span>
                        <span class="text-xs text-gray-600 tabular-nums">
                            {{ $rev->from_amount !== null ? number_format((float) $rev->from_amount, 2) : '—' }}
                            &rarr;
                            <strong class="text-gray-800">{{ number_format((float) $rev->to_amount, 2) }}</strong>
                            <span class="text-gray-500">{{ \App\Models\Employee::PAY_TYPE_SUFFIXES[$rev->to_pay_type] ?? '' }}</span>
                        </span>
                        <span class="text-xs text-gray-500">from {{ $rev->effective_on->format('d M Y') }}</span>
                        @if ($rev->reason)
                            <span class="text-xs text-gray-500 italic">{{ $rev->reason }}</span>
                        @endif
                        <span class="ml-auto text-[11px] text-gray-500">requested by {{ $rev->requester?->name ?? '—' }}</span>
                        @if ($canApprove)
                            <button wire:click="openReview({{ $rev->id }})" class="btn-secondary">Review</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-[180px]">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or staff ID…"
                       class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            </div>
            <select wire:model.live="outletFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                <option value="">All Outlets</option>
                @foreach ($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="sectionFilter" class="text-sm rounded-lg border-gray-300 shadow-sm">
                <option value="">All Sections</option>
                @foreach ($sections as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Monthly figures --}}
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-gray-700">
                {{ $summary['from']->format('F Y') }}
            </h3>
            <p class="text-xs text-gray-600">
                OT priced at {{ (float) $settings->ot_normal_multiplier }}× normal,
                {{ (float) $settings->ot_rest_day_multiplier }}× rest day,
                {{ (float) $settings->ot_public_holiday_multiplier }}× public holiday
                · hourly = salary ÷ {{ $settings->monthly_working_days }} ÷ {{ (float) $settings->daily_working_hours }}
            </p>
        </div>

      <div class="overflow-x-auto">
        <table class="table-surface min-w-[1000px]">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Section</th>
                    <th class="px-4 py-3 text-right">Basic</th>
                    <th class="px-4 py-3 text-right">Allowances</th>
                    <th class="px-4 py-3 text-right">Deductions</th>
                    <th class="px-4 py-3 text-right">OT hrs</th>
                    <th class="px-4 py-3 text-right">OT pay</th>
                    <th class="px-4 py-3 text-right">Gross</th>
                    @if ($summary['statutory']->anyEnabled())
                        <th class="px-4 py-3 text-right">EPF</th>
                        <th class="px-4 py-3 text-right">SOCSO</th>
                        <th class="px-4 py-3 text-right">EIS</th>
                        <th class="px-4 py-3 text-right">PCB</th>
                        <th class="px-4 py-3 text-right">Net</th>
                    @endif
                    <th class="px-4 py-3 text-center w-24">Manage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summary['rows'] as $row)
                    <tr wire:key="comp-{{ $row['employee_id'] }}" class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-employee-avatar :id="$row['employee_id']" :name="$row['name']"
                                                   :photo="$row['photo_path'] ?? null" />
                                <div class="min-w-0">
                                    <a href="{{ route('hr.compensation.employee', $row['employee_id']) }}"
                                       class="font-medium text-gray-800 hover:text-brand-600 hover:underline">{{ $row['name'] }}</a>
                                    @if ($row['outlet'])
                                        <div class="text-[11px] text-gray-500">{{ $row['outlet'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-sm">{{ $row['section'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                            {{ number_format($row['basic'], 2) }}
                            <span class="text-[10px] text-gray-500">{{ \App\Models\Employee::PAY_TYPE_SUFFIXES[$row['pay_type']] ?? '' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $row['allowances'] > 0 ? 'text-success-700' : 'text-gray-400' }}">
                            {{ number_format($row['allowances'], 2) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $row['deductions'] > 0 ? 'text-danger-600' : 'text-gray-400' }}">
                            {{ number_format($row['deductions'], 2) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($row['ot_hours'], 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                            @if ($row['ot_unrated'])
                                {{-- No salary on file, so any OT figure would be invented. --}}
                                <span class="text-[11px] text-warning-700">no salary set</span>
                            @else
                                {{ number_format($row['ot_amount'], 2) }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-900">{{ number_format($row['gross'], 2) }}</td>
                        @if ($summary['statutory']->anyEnabled())
                            @php $st = $row['statutory']; @endphp
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($st['epf_employee'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($st['socso_employee'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($st['eis_employee'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600">{{ number_format($st['pcb'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-900">{{ number_format($row['net'], 2) }}</td>
                        @endif
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('hr.compensation.employee', $row['employee_id']) }}"
                               class="px-2 py-1 text-xs font-medium rounded-md bg-brand-50 text-brand-700 hover:bg-brand-100">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $summary['statutory']->anyEnabled() ? 14 : 9 }}" class="px-4 py-8 text-center text-gray-600">No employees in this view.</td></tr>
                @endforelse
            </tbody>
            @if ($summary['rows']->isNotEmpty())
                <tfoot>
                    <tr class="bg-gray-50 font-semibold text-gray-800">
                        <td class="px-4 py-3" colspan="2">Total</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['basic'], 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['allowances'], 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['deductions'], 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['ot_hours'], 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['ot_amount'], 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['gross'], 2) }}</td>
                        @if ($summary['statutory']->anyEnabled())
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['epf_employee'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['socso_employee'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['eis_employee'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['pcb'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($summary['totals']['net'], 2) }}</td>
                        @endif
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
      </div>

        <div class="px-4 py-3 border-t border-gray-100 text-xs text-gray-600">
            {{-- Said plainly rather than left for someone to discover by
                 reconciling two screens that disagree. --}}
            Service charge is not included here — it is distributed from the attendance grid and is read on
            <a href="{{ route('hr.attendance') }}" class="text-brand-600 hover:underline">Attendance Record</a>.
        </div>
    </div>

    {{-- Review modal --}}
    @if ($showReview)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="$set('showReview', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-1">Review salary change</h3>
                <p class="text-xs text-gray-600 mb-4">Approving writes the new figure onto the employee on its effective date.</p>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Note</label>
                    <textarea wire:model="reviewNote" rows="3" class="mt-1 w-full text-sm rounded-lg border-gray-300"
                              placeholder="Required when rejecting"></textarea>
                    <x-input-error :messages="$errors->get('reviewNote')" class="mt-1" />
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="$set('showReview', false)" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                    <button wire:click="rejectRevision" class="btn-danger">Reject</button>
                    <button wire:click="approveRevision" class="btn-primary">Approve</button>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
