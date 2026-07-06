<?php

namespace App\Livewire\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, ScopesToActiveOutlet;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $quickRange = 'last_7';

    #[Url]
    public string $userFilter = '';

    #[Url]
    public string $outletFilter = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $eventFilter = '';

    public int $perPage = 50;

    public function mount(): void
    {
        if ($this->dateFrom === '' && $this->dateTo === '') {
            $this->applyQuickRange($this->quickRange ?: 'last_7');
        }
    }

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedUserFilter(): void    { $this->resetPage(); }
    public function updatedOutletFilter(): void  { $this->resetPage(); }
    public function updatedTypeFilter(): void    { $this->resetPage(); }
    public function updatedEventFilter(): void   { $this->resetPage(); }
    public function updatedDateFrom(): void       { $this->quickRange = ''; $this->resetPage(); }
    public function updatedDateTo(): void         { $this->quickRange = ''; $this->resetPage(); }

    public function setQuickRange(string $range): void
    {
        $this->quickRange = $range;
        $this->applyQuickRange($range);
        $this->resetPage();
    }

    private function applyQuickRange(string $range): void
    {
        $today = Carbon::today();

        [$from, $to] = match ($range) {
            'today'      => [$today, $today],
            'last_7'     => [$today->copy()->subDays(6), $today],
            'last_30'    => [$today->copy()->subDays(29), $today],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            default      => [null, null], // 'all'
        };

        $this->dateFrom = $from?->toDateString() ?? '';
        $this->dateTo   = $to?->toDateString() ?? '';
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'userFilter', 'outletFilter', 'typeFilter', 'eventFilter']);
        $this->setQuickRange('last_7');
    }

    /** Current filter state as a plain array (for the service + export links). */
    private function filters(): array
    {
        return [
            'search'    => $this->search,
            'date_from' => $this->dateFrom,
            'date_to'   => $this->dateTo,
            'user_id'   => $this->userFilter,
            'outlet_id' => $this->outletFilter,
            'type'      => $this->typeFilter,
            'event'     => $this->eventFilter,
        ];
    }

    public function render()
    {
        $user = auth()->user();

        $logs = AuditLogService::query($this->filters(), $user)->paginate($this->perPage);

        // Human-readable names for this page: record labels for the Record
        // column, FK value labels for the before/after detail tables.
        $recordLabels = AuditLogService::recordLabels($logs->items());
        $fkLabels     = AuditLogService::foreignLabels($logs->items());

        // Distinct event values present for this company, for the event filter.
        $events = AuditLog::query()
            ->select('event')->distinct()->orderBy('event')->pluck('event')->all();

        $users = User::where('company_id', $user->company_id)
            ->orderBy('name')->get(['id', 'name']);

        return view('livewire.audit.index', [
            'logs'          => $logs,
            'recordLabels'  => $recordLabels,
            'fkLabels'      => $fkLabels,
            'events'        => $events,
            'users'         => $users,
            'moduleOptions' => AuditLogService::moduleLabels(),
            'outlets'       => $this->filterableOutlets(),
            'exportParams'  => array_filter($this->filters(), fn ($v) => $v !== '' && $v !== null),
        ])->layout('layouts.app', ['title' => 'Audit Logs']);
    }
}
