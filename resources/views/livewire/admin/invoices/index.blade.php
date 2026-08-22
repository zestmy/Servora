<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div wire:key="err-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <x-page-header title="Invoices" eyebrow="Admin"
                   subtitle="Every subscription invoice raised against a customer company.">
        <x-slot:actions>
            <a href="{{ route('admin.invoices.create') }}" wire:navigate class="btn-primary">
                <x-icon name="receipt" size="h-4 w-4" />
                New invoice
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- KPIs. Deliberately unfiltered — see Index::render(). --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="card p-5">
            <div class="stat">
                <span class="stat-label">Outstanding</span>
                <span class="stat-value">{{ $currency }} {{ number_format($outstanding, 2) }}</span>
                <span class="stat-meta">Draft and issued, not yet paid</span>
            </div>
        </div>
        <div class="card p-5">
            <div class="stat">
                <span class="stat-label">Overdue</span>
                <span class="stat-value {{ $overdue > 0 ? 'text-danger-700' : '' }}">{{ $currency }} {{ number_format($overdue, 2) }}</span>
                <span class="stat-meta">Issued and past the due date</span>
            </div>
        </div>
        <div class="card p-5">
            <div class="stat">
                <span class="stat-label">Paid this month</span>
                <span class="stat-value">{{ $currency }} {{ number_format($paidThisMonth, 2) }}</span>
                <span class="stat-meta">Settled since {{ now()->startOfMonth()->format('d M') }}</span>
            </div>
        </div>
        <div class="card p-5">
            <div class="stat">
                <span class="stat-label">Drafts</span>
                <span class="stat-value">{{ number_format($draftCount) }}</span>
                <span class="stat-meta">Not yet issued to a customer</span>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="toolbar mb-4">
        <div class="flex-1 min-w-[12rem]">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Invoice number or company…"
                   class="input" aria-label="Search invoices" />
        </div>
        <select wire:model.live="statusFilter" class="input w-auto" aria-label="Status">
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="issued">Issued</option>
            <option value="overdue">Overdue</option>
            <option value="paid">Paid</option>
            <option value="void">Void</option>
        </select>
        <select wire:model.live="companyFilter" class="input w-auto" aria-label="Company">
            <option value="">All companies</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="periodFilter" class="input w-auto" aria-label="Period">
            <option value="">All time</option>
            <option value="this_month">This month</option>
            <option value="last_month">Last month</option>
            <option value="this_year">This year</option>
            <option value="last_year">Last year</option>
        </select>
        @if ($search || $statusFilter || $companyFilter || $periodFilter)
            <button wire:click="clearFilters" class="btn-ghost">Clear</button>
        @endif
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Invoice</th>
                        <th class="px-4 py-3 text-left">Company</th>
                        <th class="px-4 py-3 text-left">Plan</th>
                        <th class="px-4 py-3 text-left">Issued</th>
                        <th class="px-4 py-3 text-left">Due</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr wire:key="inv-{{ $invoice->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <button wire:click="view({{ $invoice->id }})"
                                        class="font-medium text-brand-700 hover:text-brand-800 hover:underline">
                                    {{ $invoice->invoice_number }}
                                </button>
                                @if ($invoice->sent_at)
                                    <p class="text-[11px] text-gray-500">Sent {{ $invoice->sent_at->format('d M Y') }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $invoice->companyName() }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $invoice->subscription?->plan?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $invoice->issued_at?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 {{ $invoice->isOverdue() ? 'text-danger-700 font-medium' : 'text-gray-600' }}">
                                {{ $invoice->due_at?->format('d M Y') ?? '—' }}
                                @if ($invoice->isOverdue())
                                    <span class="block text-[11px]">{{ $invoice->daysOverdue() }}d late</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900">
                                {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="badge-{{ $invoice->statusColor() === 'gray' ? 'neutral' : $invoice->statusColor() }}">
                                    {{ $invoice->statusLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($invoice->isDraft())
                                        <a href="{{ route('admin.invoices.edit', $invoice->id) }}" wire:navigate
                                           class="icon-btn" title="Edit draft" aria-label="Edit draft">
                                            <x-icon name="clipboard" size="h-4 w-4" />
                                        </a>
                                        <button wire:click="issue({{ $invoice->id }})" class="btn-secondary py-1 px-2 text-xs">Issue</button>
                                    @endif

                                    @if (! $invoice->isDraft())
                                        <a href="{{ route('invoices.pdf', $invoice->id) }}"
                                           class="icon-btn" title="Download PDF" aria-label="Download PDF">
                                            <x-icon name="download" size="h-4 w-4" />
                                        </a>
                                    @endif

                                    @if ($invoice->isOutstanding())
                                        <button wire:click="openSettle({{ $invoice->id }})"
                                                class="btn-primary py-1 px-2 text-xs">Mark paid</button>
                                        <button wire:click="openVoid({{ $invoice->id }})"
                                                class="icon-btn icon-btn-danger" title="Void" aria-label="Void invoice">
                                            <x-icon name="alert" size="h-4 w-4" />
                                        </button>
                                    @endif

                                    @if ($invoice->isDraft())
                                        <button wire:click="deleteDraft({{ $invoice->id }})"
                                                data-confirm-delete="Delete draft {{ $invoice->invoice_number }}?"
                                                class="icon-btn icon-btn-danger" title="Delete draft" aria-label="Delete draft">
                                            <x-icon name="trash" size="h-4 w-4" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12">
                                <div class="empty-state">
                                    <x-icon name="receipt" size="h-8 w-8" class="text-gray-500" />
                                    <p class="font-medium text-gray-700">No invoices match this view</p>
                                    <p class="text-xs text-gray-600">
                                        Paid subscriptions raise an invoice automatically. Raise one by hand for a
                                        bank transfer, an agreed upgrade, or a credit.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($invoices->hasPages())
        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif

    {{-- Detail drawer. Teleported for the same reason the subscriptions modal
         is: the sidebar's transform turns position:fixed into absolute. --}}
    @if ($viewing)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeView"></div>
            <div class="relative bg-white rounded-panel shadow-e4 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-6">
                    <div>
                        <p class="page-eyebrow">{{ $viewing->companyName() }}</p>
                        <h2 class="text-lg font-bold text-gray-900 mt-0.5">{{ $viewing->invoice_number }}</h2>
                        <span class="badge-{{ $viewing->statusColor() === 'gray' ? 'neutral' : $viewing->statusColor() }} mt-2">
                            {{ $viewing->statusLabel() }}
                        </span>
                    </div>
                    <button wire:click="closeView" class="icon-btn" aria-label="Close">
                        <x-icon name="chevron-right" size="h-5 w-5" />
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <dt class="stat-label">Issued</dt>
                            <dd class="text-gray-900">{{ $viewing->issued_at?->format('d M Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="stat-label">Due</dt>
                            <dd class="text-gray-900">{{ $viewing->due_at?->format('d M Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="stat-label">Paid</dt>
                            <dd class="text-gray-900">{{ $viewing->paid_at?->format('d M Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="stat-label">Plan</dt>
                            <dd class="text-gray-900">{{ $viewing->subscription?->plan?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="stat-label">Service period</dt>
                            <dd class="text-gray-900">
                                @if ($viewing->period_start && $viewing->period_end)
                                    {{ $viewing->period_start->format('d M Y') }} – {{ $viewing->period_end->format('d M Y') }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="stat-label">Raised by</dt>
                            <dd class="text-gray-900">{{ $viewing->creator?->name ?? 'System (payment)' }}</dd>
                        </div>
                    </dl>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-800 mb-2">Lines</h3>
                        <table class="table-surface w-full">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-center">Qty</th>
                                    <th class="px-3 py-2 text-right">Unit</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($viewing->line_items ?? [] as $line)
                                    <tr>
                                        <td class="px-3 py-2">{{ $line['description'] ?? '' }}</td>
                                        <td class="px-3 py-2 text-center tabular-nums">{{ floatval($line['quantity'] ?? 1) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($line['amount'] ?? 0), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-3 space-y-1 text-sm">
                            <div class="flex justify-between"><span class="text-gray-600">Subtotal</span>
                                <span class="tabular-nums">{{ $viewing->currency }} {{ number_format((float) $viewing->amount, 2) }}</span></div>
                            @if ((float) $viewing->tax_amount > 0)
                                <div class="flex justify-between"><span class="text-gray-600">{{ $viewing->tax_label ?: 'Tax' }}</span>
                                    <span class="tabular-nums">{{ $viewing->currency }} {{ number_format((float) $viewing->tax_amount, 2) }}</span></div>
                            @endif
                            <div class="flex justify-between font-semibold text-gray-900 border-t border-gray-200 pt-1">
                                <span>Total</span>
                                <span class="tabular-nums">{{ $viewing->currency }} {{ number_format((float) $viewing->total, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($viewing->notes)
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 mb-1">Notes</h3>
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $viewing->notes }}</p>
                        </div>
                    @endif

                    @if ($viewing->isVoid())
                        <div class="alert-warning">
                            <div>
                                <p class="font-medium">Voided {{ $viewing->voided_at?->format('d M Y') }}</p>
                                @if ($viewing->void_reason)
                                    <p class="mt-0.5">{{ $viewing->void_reason }}</p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 p-4">
                    @if (! $viewing->isDraft())
                        <a href="{{ route('invoices.pdf', $viewing->id) }}" class="btn-secondary">Download PDF</a>
                        @if (! $viewing->sent_at)
                            <button wire:click="markSent({{ $viewing->id }})" class="btn-secondary">Mark as sent</button>
                        @endif
                    @endif
                    @if ($viewing->isDraft())
                        <a href="{{ route('admin.invoices.edit', $viewing->id) }}" wire:navigate class="btn-secondary">Edit draft</a>
                        <button wire:click="issue({{ $viewing->id }})" class="btn-secondary">Issue</button>
                    @endif
                    @if ($viewing->isOutstanding())
                        <button wire:click="openSettle({{ $viewing->id }})" class="btn-primary">Mark paid</button>
                    @endif
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Settle --}}
    @if ($showSettle)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeSettle"></div>
            <div class="relative bg-white rounded-panel shadow-e4 w-full max-w-md p-6">
                <h2 class="text-base font-bold text-gray-900 mb-1">Record payment</h2>
                <p class="text-xs text-gray-600 mb-4">
                    This writes a completed payment against the company, so the invoice, the payment
                    history and the revenue figures all agree.
                </p>

                <div class="space-y-4">
                    <div>
                        <x-input-label for="settle_paid_at" value="Payment date *" />
                        <x-text-input id="settle_paid_at" wire:model="settle_paid_at" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('settle_paid_at')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="settle_method" value="Method *" />
                        <select id="settle_method" wire:model="settle_method" class="input mt-1">
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="manual">Other</option>
                        </select>
                        <x-input-error :messages="$errors->get('settle_method')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="settle_reference" value="Reference" />
                        <x-text-input id="settle_reference" wire:model="settle_reference" type="text"
                                      class="mt-1 block w-full" placeholder="Bank reference, cheque number…" />
                        <x-input-error :messages="$errors->get('settle_reference')" class="mt-1" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="closeSettle" class="btn-secondary">Cancel</button>
                    <button wire:click="confirmSettle" class="btn-primary">Record payment</button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Void --}}
    @if ($showVoid)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeVoid"></div>
            <div class="relative bg-white rounded-panel shadow-e4 w-full max-w-md p-6">
                <h2 class="text-base font-bold text-gray-900 mb-1">Void invoice</h2>
                <p class="text-xs text-gray-600 mb-4">
                    The row stays, marked void. Invoice numbers are a sequence — deleting one leaves
                    a gap an audit will ask about.
                </p>
                <x-input-label for="void_reason" value="Reason" />
                <textarea id="void_reason" wire:model="void_reason" rows="3" class="input mt-1"
                          placeholder="Raised in error, superseded by INV-…"></textarea>
                <x-input-error :messages="$errors->get('void_reason')" class="mt-1" />

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="closeVoid" class="btn-secondary">Cancel</button>
                    <button wire:click="confirmVoid" class="btn-danger">Void invoice</button>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
