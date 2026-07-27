<?php

namespace App\Livewire\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * System Admin: company lifecycle — suspend (blocks every member's login
 * via EnsureAccountActive), reactivate, and permanently delete a company.
 *
 * Companies with system-role members are protected from both actions:
 * the platform admins' home company must always stay operational.
 */
class Companies extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = ''; // '' | active | suspended

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    /** Company ids that contain at least one system-role member. */
    protected function protectedCompanyIds(): array
    {
        static $ids = null;
        return $ids ??= DB::table('company_user')
            ->whereIn('user_id', DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', User::class)
                ->whereIn('roles.name', ['Super Admin', 'System Admin'])
                ->select('model_has_roles.model_id'))
            ->pluck('company_id')->map(fn ($id) => (int) $id)->unique()->all();
    }

    public function toggleSuspend(int $companyId): void
    {
        if (! Auth::user()->isSystemRole()) return;

        $company = Company::find($companyId);
        if (! $company) return;
        if (in_array($companyId, $this->protectedCompanyIds(), true)) {
            session()->flash('error', 'This company has platform admin members and cannot be suspended.');
            return;
        }

        if ($company->is_active) {
            $company->update(['is_active' => false]);
            // Kick members whose ACTIVE company is this one — they are locked
            // out by EnsureAccountActive on their next request anyway.
            $memberIds = User::where('company_id', $companyId)->pluck('id');
            DB::table('sessions')->whereIn('user_id', $memberIds)->delete();
            Log::info('Admin suspended company', ['admin_id' => Auth::id(), 'company_id' => $companyId]);
            session()->flash('success', 'Company "' . $company->name . '" suspended — its members are locked out.');
        } else {
            $company->update(['is_active' => true]);
            Log::info('Admin reactivated company', ['admin_id' => Auth::id(), 'company_id' => $companyId]);
            session()->flash('success', 'Company "' . $company->name . '" reactivated.');
        }
    }

    /**
     * Delete a company. Members are handled first: multi-company users are
     * repointed to another membership and kept; users whose ONLY company
     * this is are deleted with full cleanup. The company itself SOFT
     * deletes (Company uses SoftDeletes), so its domain data is retained
     * invisibly and the platform team can restore it if the deletion was a
     * mistake — but to every user it is gone.
     */
    public function deleteCompany(int $companyId): void
    {
        if (! Auth::user()->isSystemRole()) return;

        $company = Company::find($companyId);
        if (! $company) return;
        if (in_array($companyId, $this->protectedCompanyIds(), true)) {
            session()->flash('error', 'This company has platform admin members and cannot be deleted.');
            return;
        }

        $name = $company->name;
        DB::transaction(function () use ($company, $companyId) {
            $memberIds = DB::table('company_user')->where('company_id', $companyId)->pluck('user_id')
                ->merge(User::where('company_id', $companyId)->pluck('id'))
                ->unique();

            foreach (User::whereIn('id', $memberIds)->get() as $member) {
                $otherCompanyId = $member->companies()
                    ->where('companies.id', '!=', $companyId)
                    ->value('companies.id');

                $member->companies()->detach($companyId);

                if ($otherCompanyId) {
                    if ((int) $member->company_id === $companyId) {
                        $member->update(['company_id' => $otherCompanyId, 'outlet_id' => null]);
                        $member->refreshCapabilityCache();
                    }
                } else {
                    // This was their only company — remove the account.
                    $member->outlets()->detach();
                    DB::table('kitchen_users')->where('user_id', $member->id)->delete();
                    DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $member->id)->delete();
                    DB::table('model_has_permissions')->where('model_type', User::class)->where('model_id', $member->id)->delete();
                    $member->delete();
                }
                DB::table('sessions')->where('user_id', $member->id)->delete();
            }

            // Team-scoped role/permission assignment rows for this company
            DB::table('model_has_roles')->where('team_id', $companyId)->delete();
            DB::table('model_has_permissions')->where('team_id', $companyId)->delete();

            // Soft delete: domain data stays in the database (restorable by
            // the platform team) but the company vanishes from the app.
            $company->delete();
        });

        Log::info('Admin deleted company', ['admin_id' => Auth::id(), 'company_id' => $companyId, 'name' => $name]);
        session()->flash('success', 'Company "' . $name . '" deleted. Members were removed or moved to their other companies; its data is archived and can be restored by the platform team if needed.');
    }

    public function render()
    {
        $query = Company::withCount(['users', 'outlets'])
            ->with('subscription.plan')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'suspended', fn ($q) => $q->where('is_active', false))
            ->orderBy('name');

        return view('livewire.admin.companies', [
            'companies'    => $query->paginate(20),
            'protectedIds' => $this->protectedCompanyIds(),
            'totalActive'    => Company::where('is_active', true)->count(),
            'totalSuspended' => Company::where('is_active', false)->count(),
        ])->layout('layouts.app', ['title' => 'Companies']);
    }
}
