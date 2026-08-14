<?php

namespace App\Livewire\Training;

use App\Models\Employee;
use App\Models\Outlet;
use App\Services\Training\ReportCardService;
use App\Traits\RequiresActiveCompany;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Report cards — the roster, and one person at a time.
 *
 * One screen rather than two, because the manager's flow is "who is behind →
 * why", and a page load between those two halves is where the question gets
 * dropped.
 */
class ReportCards extends Component
{
    use RequiresActiveCompany;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'outlet', except: '')]
    public string $outletId = '';

    #[Url(as: 'staff', except: '')]
    public string $selectedId = '';

    public function mount(): void
    {
        $this->requireActiveCompany();
    }

    public function select(int $id): void
    {
        $this->selectedId = (string) $id;
    }

    public function clearSelection(): void
    {
        $this->selectedId = '';
    }

    public function render(ReportCardService $service)
    {
        $companyId = Auth::user()->company_id;

        $roster = $service->roster(
            $companyId,
            $this->outletId ? (int) $this->outletId : null,
            $this->search,
        );

        $card = null;

        if ($this->selectedId) {
            // Company-scoped lookup, so a hand-typed id from another tenant is
            // simply not found rather than rendered.
            $employee = Employee::where('company_id', $companyId)->find((int) $this->selectedId);

            $card = $employee ? $service->for($employee) : null;

            if (! $employee) {
                $this->selectedId = '';
            }
        }

        $outlets = Outlet::where('company_id', $companyId)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name']);

        return view('livewire.training.report-cards', compact('roster', 'card', 'outlets'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Report cards']);
    }
}
