<?php

namespace App\Livewire\Admin\Invoices;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every subscription invoice the platform has raised.
 *
 * The list is the ledger: it is the only place that answers "who owes us
 * money, and since when". Before this screen existed, invoices were written
 * by the CHIP-IN webhook and read by nobody — there was no surface that could
 * see them at all.
 */
class Index extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $statusFilter = '';
    public string $periodFilter = '';
    public ?int   $companyFilter = null;

    /** Detail drawer. */
    public ?int $viewingId = null;

    /** Settle modal. */
    public bool    $showSettle = false;
    public ?int    $settlingId = null;
    public string  $settle_paid_at = '';
    public string  $settle_method = 'bank_transfer';
    public string  $settle_reference = '';

    /** Void modal. */
    public bool   $showVoid = false;
    public ?int   $voidingId = null;
    public string $void_reason = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPeriodFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCompanyFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'periodFilter', 'companyFilter']);
        $this->resetPage();
    }

    // ── Row actions ────────────────────────────────────────────────────────

    public function view(int $id): void
    {
        $this->viewingId = $id;
    }

    public function closeView(): void
    {
        $this->viewingId = null;
    }

    public function issue(int $id): void
    {
        $invoice = Invoice::findOrFail($id);

        if (! $invoice->isDraft()) {
            session()->flash('error', 'Only a draft invoice can be issued.');

            return;
        }

        app(InvoiceService::class)->issue($invoice);
        session()->flash('success', "{$invoice->invoice_number} issued.");
    }

    public function openSettle(int $id): void
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->isPaid() || $invoice->isVoid()) {
            session()->flash('error', 'This invoice is already closed.');

            return;
        }

        $this->settlingId       = $id;
        $this->settle_paid_at   = now()->format('Y-m-d');
        $this->settle_method    = 'bank_transfer';
        $this->settle_reference = '';
        $this->showSettle       = true;
    }

    public function confirmSettle(): void
    {
        $this->validate([
            'settle_paid_at'   => ['required', 'date'],
            'settle_method'    => ['required', 'string', 'max:40'],
            'settle_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $invoice = Invoice::findOrFail($this->settlingId);

        try {
            app(InvoiceService::class)->markPaid(
                $invoice,
                Carbon::parse($this->settle_paid_at),
                $this->settle_method,
                $this->settle_reference ?: null,
            );
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->closeSettle();

            return;
        }

        session()->flash('success', "{$invoice->invoice_number} marked paid.");
        $this->closeSettle();
    }

    public function closeSettle(): void
    {
        $this->showSettle = false;
        $this->reset(['settlingId', 'settle_paid_at', 'settle_method', 'settle_reference']);
        $this->resetValidation();
    }

    public function openVoid(int $id): void
    {
        $this->voidingId   = $id;
        $this->void_reason = '';
        $this->showVoid    = true;
    }

    public function confirmVoid(): void
    {
        $this->validate(['void_reason' => ['nullable', 'string', 'max:300']]);

        $invoice = Invoice::findOrFail($this->voidingId);

        try {
            app(InvoiceService::class)->void($invoice, $this->void_reason ?: null);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
            $this->closeVoid();

            return;
        }

        session()->flash('success', "{$invoice->invoice_number} voided.");
        $this->closeVoid();
    }

    public function closeVoid(): void
    {
        $this->showVoid = false;
        $this->reset(['voidingId', 'void_reason']);
        $this->resetValidation();
    }

    public function markSent(int $id): void
    {
        $invoice = Invoice::findOrFail($id);
        app(InvoiceService::class)->markSent($invoice);
        session()->flash('success', "{$invoice->invoice_number} marked as sent.");
    }

    /**
     * Deletable only while it is a draft. An issued number leaves a hole in
     * the sequence if the row goes, so everything past draft is voided
     * instead — see InvoiceService::void().
     */
    public function deleteDraft(int $id): void
    {
        $invoice = Invoice::findOrFail($id);

        if (! $invoice->isDraft()) {
            session()->flash('error', 'Only a draft can be deleted. Void the invoice instead.');

            return;
        }

        $number = $invoice->invoice_number;
        $invoice->delete();

        session()->flash('success', "Draft {$number} deleted.");
    }

    // ── Rendering ──────────────────────────────────────────────────────────

    private function baseQuery()
    {
        $query = Invoice::query()->with(['company', 'subscription.plan']);

        if ($this->search !== '') {
            $term = "%{$this->search}%";
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'like', $term));
            });
        }

        if ($this->companyFilter) {
            $query->where('company_id', $this->companyFilter);
        }

        if ($this->statusFilter === 'overdue') {
            $query->overdue();
        } elseif ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        // Bucketed in PHP-friendly SQL: a raw YEAR()/MONTH() here makes the
        // whole screen untestable on SQLite, so the range is computed first
        // and compared as dates.
        if ($this->periodFilter !== '') {
            [$from, $to] = $this->periodRange($this->periodFilter);
            $query->whereBetween('issued_at', [$from, $to]);
        }

        return $query;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodRange(string $key): array
    {
        return match ($key) {
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'this_year'  => [now()->startOfYear(), now()->endOfYear()],
            'last_year'  => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            default      => [now()->startOfCentury(), now()->endOfCentury()],
        };
    }

    public function render()
    {
        $invoices = $this->baseQuery()
            // Drafts have no issued_at, so ordering on it alone drops them to
            // the bottom of the list — where the one thing needing attention
            // is the hardest to find. id desc keeps newest-first regardless.
            ->orderByDesc('id')
            ->paginate(20);

        $currency = app(InvoiceService::class)->defaultCurrency();

        // Unfiltered: these are the platform's numbers, not the current
        // filter's, and a KPI that moves when you type in a search box is a
        // KPI nobody trusts.
        $outstanding = Invoice::outstanding()->sum('total');
        $overdue     = Invoice::overdue()->sum('total');
        $paidThisMonth = Invoice::paid()
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');
        $draftCount = Invoice::where('status', Invoice::STATUS_DRAFT)->count();

        return view('livewire.admin.invoices.index', [
            'invoices'      => $invoices,
            'viewing'       => $this->viewingId
                ? Invoice::with(['company', 'subscription.plan', 'payment', 'creator'])->find($this->viewingId)
                : null,
            'companies'     => Company::orderBy('name')->get(['id', 'name']),
            'currency'      => $currency,
            'outstanding'   => $outstanding,
            'overdue'       => $overdue,
            'paidThisMonth' => $paidThisMonth,
            'draftCount'    => $draftCount,
        ])->layout('layouts.app', ['title' => 'Invoices']);
    }
}
