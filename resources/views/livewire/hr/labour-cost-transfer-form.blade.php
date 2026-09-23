<div>
    <x-page-header eyebrow="HR / Labour Cost Transfer"
                   :title="$transferId ? $transfer_number : 'New Labour Cost Transfer'"
                   subtitle="Charge an outlet for staff lent to it — by the day, by the hour, or overtime only — plus approved overtime in the same dates.">
        <x-slot:actions>
            <a data-back href="{{ route('hr.labour-transfers') }}" class="btn-ghost">Back</a>
            @if ($transferId)
                <a href="{{ route('hr.labour-transfers.pdf', $transferId) }}" class="btn-secondary">
                    <x-icon name="download" class="h-4 w-4" /> PDF
                </a>
                <a href="{{ route('hr.labour-transfers.excel', $transferId) }}" class="btn-secondary">
                    <x-icon name="download" class="h-4 w-4" /> Excel
                </a>
            @endif
            @if ($status === 'draft')
                <button wire:click="save" class="btn-secondary">Save draft</button>
                <button wire:click="confirm" wire:confirm="Confirm this transfer? The figures are locked once confirmed." class="btn-primary">Confirm</button>
            @elseif ($editing)
                <a href="{{ route('hr.labour-transfers.show', $transferId) }}" class="btn-ghost">Discard changes</a>
                <button wire:click="saveChanges" wire:confirm="Save these changes? The lines are recalculated, and the labour reports change with them." class="btn-primary">Save changes</button>
            @elseif ($status === 'confirmed' && $canManage)
                <button wire:click="startEditing" class="btn-secondary">
                    <x-icon name="pencil" class="h-4 w-4" /> Edit
                </button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (session()->has('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-danger mb-4">
            <p class="font-medium mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Details --}}
        <div class="lg:col-span-2 card p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Transfer details</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="lct_number">Transfer #</label>
                    <input id="lct_number" type="text" value="{{ $transfer_number }}" class="input w-full bg-gray-50" readonly />
                </div>
                <div>
                    <label class="label" for="lct_date">Date <span class="text-danger-600">*</span></label>
                    <input id="lct_date" type="date" wire:model="transfer_date" class="input w-full" @disabled(! $isDraft) />
                </div>
                <div>
                    <label class="label" for="lct_to">Charge to outlet <span class="text-danger-600">*</span></label>
                    <select id="lct_to" wire:model.live="to_outlet_id" class="input w-full" @disabled(! $isDraft)>
                        <option value="">Select the outlet taking on the cost…</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                    <p class="help">The outlet the staff worked for — for an event or catering job, the outlet running it.</p>
                </div>
                <div>
                    <label class="label" for="lct_purpose">Purpose <span class="text-danger-600">*</span></label>
                    <select id="lct_purpose" wire:model="purpose" class="input w-full" @disabled(! $isDraft)>
                        @foreach (\App\Models\LabourCostTransfer::PURPOSES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="lct_ref">Event / reference</label>
                    <input id="lct_ref" type="text" wire:model="reference" maxlength="200" class="input w-full"
                           placeholder="e.g. Wedding catering — Dewan Seri, or covering for a short-staffed week" @disabled(! $isDraft) />
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="lct_notes">Notes</label>
                    <textarea id="lct_notes" wire:model="notes" rows="2" class="input w-full" @disabled(! $isDraft)></textarea>
                </div>
            </div>
        </div>

        {{-- Totals --}}
        <div class="card p-6 lg:sticky lg:top-6 self-start">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Cost moved</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-600">Status</dt>
                    <dd>
                        <span class="{{ match ($status) { 'confirmed' => 'badge-success', 'cancelled' => 'badge-danger', default => 'badge-neutral' } }}">
                            {{ \App\Models\LabourCostTransfer::STATUSES[$status] ?? $status }}
                        </span>
                    </dd>
                </div>
                <div class="flex justify-between"><dt class="text-gray-600">Employees</dt><dd class="font-medium tabular-nums">{{ collect($lines)->pluck('employee_id')->unique()->count() }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">Days</dt><dd class="font-medium tabular-nums">{{ number_format($totals['days'], 1) }}</dd></div>
                @if ($totals['hours'] > 0)
                    <div class="flex justify-between"><dt class="text-gray-600">Hours</dt><dd class="font-medium tabular-nums">{{ number_format($totals['hours'], 2) }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-gray-600">Salary</dt><dd class="font-medium tabular-nums">RM {{ number_format($totals['salary'], 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-600">Overtime ({{ number_format($totals['ot'], 2) }} h)</dt><dd class="font-medium tabular-nums">RM {{ number_format($totals['ot_amt'], 2) }}</dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-3">
                    <dt class="font-semibold text-gray-700">Total</dt>
                    <dd class="font-bold text-lg text-brand-700 tabular-nums">RM {{ number_format($totals['total'], 2) }}</dd>
                </div>
            </dl>

            @if ($editing)
                <p class="mt-4 pt-4 border-t border-gray-100 text-xs text-warning-700">
                    Editing a confirmed transfer. Saving recalculates every line from the salary and approved OT on file now, and is recorded in the activity below.
                </p>
            @endif

            @if ($transferId && ! $editing && ($status !== 'cancelled' || $canManage))
                <div class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                    @if ($status !== 'cancelled')
                        <button wire:click="cancelTransfer" wire:confirm="Cancel this transfer?" class="btn-secondary w-full">Cancel transfer</button>
                    @endif
                    @if ($status === 'draft' || $canManage)
                        <button wire:click="deleteTransfer"
                                wire:confirm="{{ $status === 'draft' ? 'Delete this draft? This cannot be undone.' : 'Delete this ' . $status . ' transfer? Its cost comes out of the labour reports. This cannot be undone.' }}"
                                class="btn-danger w-full">Delete transfer</button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Employees --}}
    <div class="mt-4 card">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Employees</h3>
            <p class="text-xs text-gray-600 mt-0.5">
                Choose how each line is costed: <strong class="font-medium text-gray-700">Daily</strong> (days × daily rate — days default to every day in the range, lower them to leave out rest days),
                <strong class="font-medium text-gray-700">Hourly</strong> (hours × hourly rate, for a few hours' help) or <strong class="font-medium text-gray-700">OT only</strong>.
                Every basis adds the approved overtime (settled in payroll) inside the dates; an OT claim already on another line or transfer is not counted twice.
            </p>
        </div>

        @if ($isDraft)
            <div class="px-6 py-4 border-b border-gray-100">
                <div class="relative">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <x-icon name="magnifier" class="h-4 w-4 text-gray-600" />
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="employeeSearch"
                           placeholder="Search employees by name or staff ID…"
                           aria-label="Search employees"
                           class="input w-full pl-9" />
                </div>

                @if ($employeeResults->isNotEmpty())
                    <div class="mt-2 border border-gray-200 rounded-control overflow-hidden divide-y divide-gray-100 shadow-e1">
                        @foreach ($employeeResults as $emp)
                            <button type="button" wire:key="emp-{{ $emp->id }}" wire:click="addEmployee({{ $emp->id }})"
                                    class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-brand-50 transition text-left">
                                <span class="text-sm">
                                    <span class="font-medium text-gray-800">{{ $emp->name }}</span>
                                    @if ($emp->staff_id)<span class="text-xs text-gray-600"> · {{ $emp->staff_id }}</span>@endif
                                    @if ($emp->designation)<span class="text-xs text-gray-600"> · {{ $emp->designation }}</span>@endif
                                </span>
                                <span class="text-xs text-gray-600 flex-shrink-0 ml-4">{{ $emp->outlet?->name }} <span class="ml-2 text-brand-600">+ Add</span></span>
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen(trim($employeeSearch)) >= 2)
                    <p class="mt-2 text-sm text-gray-600 text-center py-2">No active employee matches at the outlets you can access.</p>
                @endif
            </div>
        @endif

        @if (count($lines))
            <div class="overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Employee</th>
                            <th class="px-4 py-2 text-left">From outlet</th>
                            <th class="px-4 py-2 text-left w-36">Start</th>
                            <th class="px-4 py-2 text-left w-36">End</th>
                            <th class="px-4 py-2 text-left w-32">Basis</th>
                            <th class="px-4 py-2 text-right w-24">Days / Hrs</th>
                            <th class="px-4 py-2 text-right">Rate</th>
                            <th class="px-4 py-2 text-right">Salary</th>
                            <th class="px-4 py-2 text-right">OT hrs</th>
                            <th class="px-4 py-2 text-right">OT cost</th>
                            <th class="px-4 py-2 text-right">Total (RM)</th>
                            @if ($isDraft)<th class="px-2 py-2 w-10"></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $idx => $line)
                            <tr wire:key="lct-line-{{ $idx }}-{{ $line['employee_id'] }}" class="group align-top">
                                <td class="px-4 py-2">
                                    <p class="font-medium text-gray-800">{{ $line['employee_name'] }}</p>
                                    @if ($line['staff_id'])<p class="text-xs text-gray-600">{{ $line['staff_id'] }}</p>@endif
                                    @if (! ($line['has_salary'] ?? true))
                                        <p class="text-xs text-warning-700 mt-0.5">No salary on file — rates are 0.</p>
                                    @endif
                                    @if (($line['basis'] ?? 'daily') === 'ot_only' && (float) ($line['ot_hours'] ?? 0) <= 0)
                                        <p class="text-xs text-warning-700 mt-0.5">No untransferred approved OT in these dates.</p>
                                    @endif
                                    @if (! empty($line['overlap']))
                                        <p class="text-xs text-danger-600 mt-0.5">Already on {{ $line['overlap'] }} for some of these dates.</p>
                                    @endif
                                    @if ((int) $line['from_outlet_id'] === (int) $to_outlet_id)
                                        <p class="text-xs text-danger-600 mt-0.5">Belongs to the receiving outlet.</p>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ $line['from_outlet_name'] }}</td>
                                <td class="px-4 py-2">
                                    @if ($isDraft)
                                        <input type="date" wire:model.blur="lines.{{ $idx }}.date_start" aria-label="Start date" class="input w-full" />
                                    @else
                                        <span class="text-sm">{{ \Illuminate\Support\Carbon::parse($line['date_start'])->format('d M Y') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($isDraft)
                                        <input type="date" wire:model.blur="lines.{{ $idx }}.date_end" aria-label="End date" class="input w-full" />
                                    @else
                                        <span class="text-sm">{{ \Illuminate\Support\Carbon::parse($line['date_end'])->format('d M Y') }}</span>
                                    @endif
                                </td>
                                @php $basis = $line['basis'] ?? 'daily'; @endphp
                                <td class="px-4 py-2">
                                    @if ($isDraft)
                                        <select wire:model.live="lines.{{ $idx }}.basis" aria-label="Costing basis" class="input w-full">
                                            @foreach (\App\Models\LabourCostTransferLine::BASES as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="text-sm">{{ \App\Models\LabourCostTransferLine::BASES[$basis] ?? $basis }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @if ($basis === 'ot_only')
                                        <span class="text-gray-600">—</span>
                                    @elseif ($isDraft)
                                        @if ($basis === 'hourly')
                                            <input type="number" step="0.25" min="0.25" wire:model.blur="lines.{{ $idx }}.hours" aria-label="Hours" class="input w-full text-right" />
                                            <span class="block text-xs text-gray-600 mt-0.5">hours</span>
                                        @else
                                            <input type="number" step="0.5" min="0.5" wire:model.blur="lines.{{ $idx }}.days" aria-label="Days" class="input w-full text-right" />
                                            <span class="block text-xs text-gray-600 mt-0.5">days</span>
                                        @endif
                                    @else
                                        <span class="tabular-nums">{{ rtrim(rtrim(number_format((float) ($basis === 'hourly' ? $line['hours'] : $line['days']), 2), '0'), '.') }}</span>
                                        <span class="text-xs text-gray-600">{{ $basis === 'hourly' ? 'hrs' : 'days' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-600" title="From the salary on file and the company's working-days and working-hours settings.">
                                    @if ($basis === 'ot_only')
                                        —
                                    @elseif ($basis === 'hourly')
                                        {{ number_format((float) ($line['hourly_rate'] ?? 0), 2) }}<span class="text-xs"> /hr</span>
                                    @else
                                        {{ number_format((float) ($line['daily_rate'] ?? 0), 2) }}<span class="text-xs"> /day</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) ($line['salary_amount'] ?? 0), 2) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums" title="{{ ($line['ot_claims'] ?? 0) }} approved claim(s)">
                                    {{ number_format((float) ($line['ot_hours'] ?? 0), 2) }}
                                    @if (($line['ot_claims'] ?? 0) > 0)
                                        <span class="block text-xs text-gray-600">{{ $line['ot_claims'] }} claim{{ $line['ot_claims'] === 1 ? '' : 's' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) ($line['ot_amount'] ?? 0), 2) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold text-brand-700">{{ number_format((float) ($line['total_amount'] ?? 0), 2) }}</td>
                                @if ($isDraft)
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" wire:click="removeLine({{ $idx }})" class="icon-btn" aria-label="Remove {{ $line['employee_name'] }}">
                                            <x-icon name="close" class="h-4 w-4 text-danger-600" />
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 text-sm font-semibold">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right text-gray-600">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums text-xs">
                                @if ($totals['days'] > 0){{ number_format($totals['days'], 1) }} days @endif
                                @if ($totals['hours'] > 0)<span class="block">{{ number_format($totals['hours'], 2) }} hrs</span>@endif
                            </td>
                            <td></td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($totals['salary'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($totals['ot'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($totals['ot_amt'], 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-brand-700">{{ number_format($totals['total'], 2) }}</td>
                            @if ($isDraft)<td></td>@endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="empty-state py-12">
                <p class="font-medium">No employees added yet</p>
                <p class="text-xs mt-1">Search above to add the staff who were lent out.</p>
            </div>
        @endif
    </div>

    {{-- Summary by outlet --}}
    @if (count($summary) && $to_outlet_id)
        <div class="mt-4 card">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Summary by outlet</h3>
                <p class="text-xs text-gray-600 mt-0.5">What each outlet gives up and takes on. Senders are credited (−), the receiver is charged (+); the column nets to zero.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Outlet</th>
                            <th class="px-4 py-2 text-right">Staff sent</th>
                            <th class="px-4 py-2 text-right">Days</th>
                            <th class="px-4 py-2 text-right">Hours</th>
                            <th class="px-4 py-2 text-right">OT hrs</th>
                            <th class="px-4 py-2 text-right">Salary out</th>
                            <th class="px-4 py-2 text-right">OT out</th>
                            <th class="px-4 py-2 text-right">Received</th>
                            <th class="px-4 py-2 text-right">Net labour cost (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary as $row)
                            <tr wire:key="sum-{{ $row['outlet_id'] }}">
                                <td class="px-4 py-2 font-medium text-gray-800">{{ $outletNames[$row['outlet_id']] ?? '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['staff'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['days'] ? number_format($row['days'], 1) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['hours'] ? number_format($row['hours'], 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['ot_hours'] ? number_format($row['ot_hours'], 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['salary_out'] ? number_format($row['salary_out'], 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['ot_out'] ? number_format($row['ot_out'], 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['received'] ? number_format($row['received'], 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold {{ $row['net'] > 0 ? 'text-danger-700' : ($row['net'] < 0 ? 'text-success-700' : 'text-gray-600') }}">
                                    {{ $row['net'] > 0 ? '+' : '' }}{{ number_format($row['net'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Recent activity, including admin corrections to a confirmed transfer --}}
    <x-audit-timeline :type="\App\Models\LabourCostTransfer::class" :id="$transferId" title="Transfer Activity" class="mt-4" />
</div>
