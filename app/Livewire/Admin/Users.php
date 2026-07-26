<?php

namespace App\Livewire\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * System Admin: cross-company user directory. Unlike Settings > Users
 * (scoped to the active company), this lists every account with ALL the
 * companies it belongs to (company_user pivot), its roles across teams,
 * verification state and last activity.
 */
class Users extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $companyFilter = '';
    public string $roleFilter    = '';
    public string $typeFilter    = ''; // '' | multi | system | unverified

    public function updatingSearch(): void        { $this->resetPage(); }
    public function updatingCompanyFilter(): void { $this->resetPage(); }
    public function updatingRoleFilter(): void    { $this->resetPage(); }
    public function updatingTypeFilter(): void    { $this->resetPage(); }

    /** Base subquery: user ids holding a system-level role (any team). */
    protected function systemRoleUserIds()
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('roles.name', ['Super Admin', 'System Admin'])
            ->select('model_has_roles.model_id');
    }

    public function render()
    {
        $query = User::query()
            ->with('companies:companies.id,name')
            ->withCount('companies');

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('email', 'like', $s)
                  ->orWhere('designation', 'like', $s);
            });
        }
        if ($this->companyFilter !== '') {
            $cid = (int) $this->companyFilter;
            // Match membership via the pivot OR the active-company pointer, so
            // legacy accounts without a pivot row still show under their company.
            $query->where(function ($q) use ($cid) {
                $q->whereHas('companies', fn ($qq) => $qq->where('companies.id', $cid))
                  ->orWhere('company_id', $cid);
            });
        }
        if ($this->roleFilter !== '') {
            $roleName = $this->roleFilter;
            $query->whereIn('id', function ($sub) use ($roleName) {
                $sub->select('model_has_roles.model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('roles.name', $roleName);
            });
        }
        if ($this->typeFilter === 'multi') {
            $query->has('companies', '>=', 2);
        } elseif ($this->typeFilter === 'system') {
            $query->whereIn('id', $this->systemRoleUserIds());
        } elseif ($this->typeFilter === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        $users = $query->orderBy('name')->paginate(25);
        $ids   = collect($users->items())->pluck('id');

        // Distinct role names per visible user, team-agnostic (Spatie teams
        // mode means hasRole()/getRoleNames() only see the ACTIVE company).
        $rolesByUser = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.model_id', $ids)
            ->select('model_has_roles.model_id as user_id', 'roles.name')
            ->distinct()
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('name')->unique()->sort()->values());

        // Last activity per visible user (database session driver).
        $lastActive = DB::table('sessions')
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(last_activity) as la')
            ->pluck('la', 'user_id')
            ->map(fn ($ts) => \Carbon\Carbon::createFromTimestamp($ts));

        return view('livewire.admin.users', [
            'users'        => $users,
            'rolesByUser'  => $rolesByUser,
            'lastActive'   => $lastActive,
            'companies'    => Company::orderBy('name')->get(['id', 'name']),
            'roleOptions'  => DB::table('roles')->distinct()->orderBy('name')->pluck('name'),
            'totalUsers'   => User::count(),
            'multiCompany' => User::has('companies', '>=', 2)->count(),
            'systemAdmins' => User::whereIn('id', $this->systemRoleUserIds())->count(),
            'unverified'   => User::whereNull('email_verified_at')->count(),
        ])->layout('layouts.app', ['title' => 'All Users']);
    }
}
