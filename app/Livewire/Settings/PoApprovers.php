<?php

namespace App\Livewire\Settings;

use App\Models\Department;
use App\Models\Outlet;
use App\Models\PoApprover;
use App\Models\PrApprover;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class PoApprovers extends Component
{
    // Settings toggles
    public bool $requirePoApproval = true;
    public string $orderingMode = 'direct';
    public bool $requirePrApproval = false;

    // Unified add/edit modal
    public bool $showModal = false;
    public string $modalType = 'po'; // 'po' or 'pr'
    public ?int $editUserId = null;
    public ?int $selectedUserId = null;
    public bool $allOutlets = false;
    public array $selectedOutletIds = [];
    public bool $allDepartments = false;
    public array $selectedDepartmentIds = [];

    public function mount(): void
    {
        $company = Auth::user()->company;
        $this->requirePoApproval = $company?->require_po_approval ?? true;
        $this->orderingMode = $company?->ordering_mode ?? 'direct';
        $this->requirePrApproval = $company?->require_pr_approval ?? false;
    }

    public function updatedRequirePoApproval(): void
    {
        Auth::user()->company->update(['require_po_approval' => $this->requirePoApproval]);
        session()->flash('success', $this->requirePoApproval
            ? 'PO approval is now required.'
            : 'PO approval is no longer required — POs will be auto-approved on submission.');
    }

    public function updatedOrderingMode(): void
    {
        Auth::user()->company->update(['ordering_mode' => $this->orderingMode]);
        session()->flash('success', $this->orderingMode === 'cpu'
            ? 'Ordering mode set to CPU — outlets will create Purchase Requests instead of direct POs.'
            : 'Ordering mode set to Direct — outlets create POs directly to suppliers.');
    }

    public function updatedRequirePrApproval(): void
    {
        Auth::user()->company->update(['require_pr_approval' => $this->requirePrApproval]);
        session()->flash('success', $this->requirePrApproval
            ? 'PR approval is now required.'
            : 'PR approval is no longer required — PRs will be auto-approved on submission.');
    }

    // ── Modal ────────────────────────────────────────────────────────────────

    public function openAdd(string $type): void
    {
        $this->resetModal();
        $this->modalType = $type;
        $this->showModal = true;
    }

    public function openEdit(string $type, int $userId): void
    {
        $this->resetModal();
        $this->modalType = $type;
        $this->editUserId = $userId;
        $this->selectedUserId = $userId;

        $model = $type === 'po' ? PoApprover::class : PrApprover::class;
        $records = $model::where('user_id', $userId)->get();

        $outletIds = $records->pluck('outlet_id')->filter()->unique()->toArray();
        $deptIds = $records->pluck('department_id')->filter()->unique()->toArray();

        // Check if user has all outlets/departments
        $companyId = Auth::user()->company_id;
        $totalOutlets = Outlet::where('company_id', $companyId)->where('is_active', true)->count();
        $totalDepts = Department::where('is_active', true)->count();

        $this->allOutlets = count($outletIds) >= $totalOutlets && $totalOutlets > 0;
        $this->selectedOutletIds = array_map('strval', $outletIds);
        $this->allDepartments = count($deptIds) >= $totalDepts && $totalDepts > 0;
        $this->selectedDepartmentIds = array_map('strval', $deptIds);

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'selectedUserId' => 'required|exists:users,id',
        ]);

        $companyId = Auth::user()->company_id;
        $model = $this->modalType === 'po' ? PoApprover::class : PrApprover::class;
        $label = $this->modalType === 'po' ? 'PO' : 'PR';

        $outlets = $this->allOutlets
            ? Outlet::where('company_id', $companyId)->where('is_active', true)->pluck('id')->toArray()
            : array_map('intval', $this->selectedOutletIds);

        $departments = $this->allDepartments
            ? Department::where('is_active', true)->pluck('id')->toArray()
            : array_map('intval', $this->selectedDepartmentIds);

        if (empty($outlets)) {
            $this->addError('selectedOutletIds', 'Select at least one outlet.');
            return;
        }
        if (empty($departments)) {
            $this->addError('selectedDepartmentIds', 'Select at least one department.');
            return;
        }

        DB::transaction(function () use ($model, $companyId, $outlets, $departments) {
            // Remove old assignments for this user if editing
            $model::where('user_id', $this->selectedUserId)->delete();

            // Create new assignments for each outlet × department combination
            foreach ($outlets as $outletId) {
                foreach ($departments as $deptId) {
                    $model::create([
                        'company_id'    => $companyId,
                        'outlet_id'     => $outletId,
                        'department_id' => $deptId,
                        'user_id'       => $this->selectedUserId,
                        'assigned_by'   => Auth::id(),
                    ]);
                }
            }
        });

        $user = User::find($this->selectedUserId);
        $outletLabel = $this->allOutlets ? 'all outlets' : count($outlets) . ' ' . Str::plural('outlet', count($outlets));
        $deptLabel = $this->allDepartments ? 'all departments' : count($departments) . ' ' . Str::plural('department', count($departments));

        session()->flash('success', "{$user->name} assigned as {$label} approver for {$outletLabel}, {$deptLabel}.");
        $this->showModal = false;
    }

    public function removeApprover(string $type, int $userId): void
    {
        $model = $type === 'po' ? PoApprover::class : PrApprover::class;
        $model::where('user_id', $userId)->delete();
        session()->flash('success', 'Approver removed.');
    }

    private function resetModal(): void
    {
        $this->editUserId = null;
        $this->selectedUserId = null;
        $this->allOutlets = false;
        $this->selectedOutletIds = [];
        $this->allDepartments = false;
        $this->selectedDepartmentIds = [];
        $this->resetErrorBag();
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $outlets = Outlet::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $departments = Department::where('is_active', true)->ordered()->get();
        $users = User::where('company_id', $companyId)->orderBy('name')->get();

        // Build PO approver summary: grouped by user
        $poApprovers = PoApprover::with(['user', 'outlet', 'department'])
            ->get()
            ->groupBy('user_id')
            ->map(function ($records) use ($outlets, $departments) {
                $user = $records->first()->user;
                $outletIds = $records->pluck('outlet_id')->unique();
                $deptIds = $records->pluck('department_id')->unique();
                $isAllOutlets = $outletIds->count() >= $outlets->count() && $outlets->count() > 0;
                $isAllDepts = $deptIds->count() >= $departments->count() && $departments->count() > 0;

                return [
                    'user'           => $user,
                    'outlets'        => $isAllOutlets ? 'All Outlets' : $records->pluck('outlet.name')->unique()->filter()->implode(', '),
                    'departments'    => $isAllDepts ? 'All Departments' : $records->pluck('department.name')->unique()->filter()->implode(', '),
                    'is_all_outlets' => $isAllOutlets,
                    'is_all_depts'   => $isAllDepts,
                    'count'          => $records->count(),
                ];
            });

        // Build PR approver summary: grouped by user
        $prApprovers = PrApprover::with(['user', 'outlet', 'department'])
            ->get()
            ->groupBy('user_id')
            ->map(function ($records) use ($outlets, $departments) {
                $user = $records->first()->user;
                $outletIds = $records->pluck('outlet_id')->unique();
                $deptIds = $records->pluck('department_id')->unique();
                $isAllOutlets = $outletIds->count() >= $outlets->count() && $outlets->count() > 0;
                $isAllDepts = $deptIds->count() >= $departments->count() && $departments->count() > 0;

                return [
                    'user'           => $user,
                    'outlets'        => $isAllOutlets ? 'All Outlets' : $records->pluck('outlet.name')->unique()->filter()->implode(', '),
                    'departments'    => $isAllDepts ? 'All Departments' : $records->pluck('department.name')->unique()->filter()->implode(', '),
                    'is_all_outlets' => $isAllOutlets,
                    'is_all_depts'   => $isAllDepts,
                    'count'          => $records->count(),
                ];
            });

        return view('livewire.settings.po-approvers', compact(
            'outlets', 'departments', 'users', 'poApprovers', 'prApprovers'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Approvers']);
    }
}
