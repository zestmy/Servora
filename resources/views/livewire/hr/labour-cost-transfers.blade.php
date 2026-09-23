<div>
    <x-page-header eyebrow="HR / Pay" title="Labour Cost Transfer"
                   subtitle="Salary and overtime moved to the outlet that borrowed the staff — outlet support, events and outside catering. Confirmed transfers move this cost between outlets in the labour reports.">
        <x-slot:actions>
            <a href="{{ route('hr.labour-transfers.summary-pdf', array_filter(['from' => $from, 'to' => $to, 'outlet' => $outlet])) }}" class="btn-secondary">
                <x-icon name="download" class="h-4 w-4" /> Summary PDF
            </a>
            <a href="{{ route('hr.labour-transfers.summary-excel', array_filter(['from' => $from, 'to' => $to, 'outlet' => $outlet])) }}" class="btn-secondary">
                <x-icon name="download" class="h-4 w-4" /> Excel
            </a>
            <a href="{{ route('hr.labour-transfers.create') }}" class="btn-primary">+ New transfer</a>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <div class="toolbar mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="lct_from">From</label>
                <input id="lct_from" type="date" wire:model.live="from" class="input" />
            </div>
            <div>
                <label class="label" for="lct_to">To</label>
                <input id="lct_to" type="date" wire:model.live="to" class="input" />
            </div>
            <div>
                <label class="label" for="lct_outlet">Outlet</label>
                <select id="lct_outlet" wire:model.live="outlet" class="input">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="lct_status">Status</label>
                <select id="lct_status" wire:model.live="status" class="input">
                    <option value="">All</option>
                    @foreach (\App\Models\LabourCostTransfer::STATUSES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        @if ($transfers->count())
            <div class="overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Transfer #</th>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Charged to</th>
                            <th class="px-4 py-2 text-left">Purpose</th>
                            <th class="px-4 py-2 text-right">Staff lines</th>
                            <th class="px-4 py-2 text-right">Total (RM)</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transfers as $t)
                            <tr wire:key="lct-{{ $t->id }}">
                                <td class="px-4 py-2 font-medium">
                                    <a href="{{ route('hr.labour-transfers.show', $t->id) }}" class="text-brand-700 hover:underline">{{ $t->transfer_number }}</a>
                                </td>
                                <td class="px-4 py-2 text-sm">{{ $t->transfer_date->format('d M Y') }}</td>
                                <td class="px-4 py-2 text-sm">{{ $t->toOutlet?->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm">
                                    {{ $t->purposeLabel() }}
                                    @if ($t->reference)<span class="block text-xs text-gray-600">{{ \Illuminate\Support\Str::limit($t->reference, 50) }}</span>@endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $t->lines_count }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold">{{ number_format((float) $t->lines_sum_total_amount, 2) }}</td>
                                <td class="px-4 py-2">
                                    <span class="{{ match ($t->status) { 'confirmed' => 'badge-success', 'cancelled' => 'badge-danger', default => 'badge-neutral' } }}">{{ $t->statusLabel() }}</span>
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('hr.labour-transfers.pdf', $t->id) }}" class="icon-btn" aria-label="Download PDF for {{ $t->transfer_number }}" title="Download PDF">
                                        <x-icon name="download" class="h-4 w-4" />
                                    </a>
                                    <a href="{{ route('hr.labour-transfers.excel', $t->id) }}" class="icon-btn" aria-label="Download Excel for {{ $t->transfer_number }}" title="Download Excel">
                                        <x-icon name="document" class="h-4 w-4 text-success-700" />
                                    </a>
                                    @if ($t->status === 'draft' || $canManage)
                                        <button type="button" wire:click="deleteTransfer({{ $t->id }})"
                                                wire:confirm="{{ $t->status === 'draft' ? 'Delete this draft? This cannot be undone.' : 'Delete this ' . $t->status . ' transfer? Its cost comes out of the labour reports. This cannot be undone.' }}"
                                                class="icon-btn" aria-label="Delete {{ $t->transfer_number }}" title="Delete">
                                            <x-icon name="trash" class="h-4 w-4 text-danger-600" />
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $transfers->links() }}</div>
        @else
            <div class="empty-state py-12">
                <p class="font-medium">No labour cost transfers in this period</p>
                <p class="text-xs mt-1">Record one when staff are lent to another outlet, an event or a catering job.</p>
            </div>
        @endif
    </div>

    {{-- Period summary --}}
    <div class="mt-4 card">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700">Summary by outlet</h3>
            <p class="text-xs text-gray-600 mt-0.5">Confirmed transfers in the period. Net is the labour cost each outlet takes on (+) or hands off (−).</p>
        </div>
        @if (count($summary))
            <div class="overflow-x-auto">
                <table class="table-surface min-w-full">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left">Outlet</th>
                            <th class="px-4 py-2 text-right">Staff sent</th>
                            <th class="px-4 py-2 text-right">Days</th>
                            <th class="px-4 py-2 text-right">Hours</th>
                            <th class="px-4 py-2 text-right">OT hrs</th>
                            <th class="px-4 py-2 text-right">Sent (RM)</th>
                            <th class="px-4 py-2 text-right">Received (RM)</th>
                            <th class="px-4 py-2 text-right">Net (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary as $row)
                            <tr wire:key="psum-{{ $row['outlet_id'] }}">
                                <td class="px-4 py-2 font-medium text-gray-800">{{ $outletNames[$row['outlet_id']] ?? '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['staff'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['days'] ? number_format($row['days'], 1) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['hours'] ? number_format($row['hours'], 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['ot_hours'] ? number_format($row['ot_hours'], 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['sent'] ? number_format($row['sent'], 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $row['received'] ? number_format($row['received'], 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold {{ $row['net'] > 0 ? 'text-danger-700' : ($row['net'] < 0 ? 'text-success-700' : 'text-gray-600') }}">
                                    {{ $row['net'] > 0 ? '+' : '' }}{{ number_format($row['net'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="px-6 py-6 text-sm text-gray-600">No confirmed transfers in this period.</p>
        @endif
    </div>
</div>
