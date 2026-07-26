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

    /**
     * Log in as another user for support. The admin's id is kept in the
     * session so the banner's "Return to admin" can restore the original
     * login; system-role accounts can never be impersonated and
     * impersonation cannot be nested.
     */
    public function impersonate(int $userId): void
    {
        $admin = Auth::user();
        if (! $admin->isSystemRole() || session()->has('impersonator_id')) {
            return;
        }

        $target = User::find($userId);
        if (! $target || $target->id === $admin->id) {
            return;
        }
        if ($target->hasGlobalRole(['Super Admin', 'System Admin'])) {
            session()->flash('error', 'System-level accounts cannot be impersonated.');
            return;
        }

        // The app assumes an active company — repoint to the first membership
        // if the pointer is empty, and refuse accounts with no company at all.
        if (! $target->company_id) {
            $firstCompanyId = $target->companies()->value('companies.id');
            if (! $firstCompanyId) {
                session()->flash('error', 'This user has no company membership to impersonate into.');
                return;
            }
            $target->switchToCompany((int) $firstCompanyId);
        }

        Log::info('Impersonation started', [
            'admin_id' => $admin->id, 'admin_email' => $admin->email,
            'target_id' => $target->id, 'target_email' => $target->email,
        ]);

        session()->put('impersonator_id', $admin->id);
        // Outlet / workspace state belongs to the admin's session — reset it.
        session()->forget(['active_outlet_id', 'workspace_mode', 'active_kitchen_id']);
        Auth::login($target);

        $this->redirect(route('dashboard'));
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
            ->selectRaw('model_has_roles.model_id as user_id, roles.name, COALESCE(roles.display_name, roles.name) as label')
            ->distinct()
            ->get()
            ->groupBy('user_id');

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
