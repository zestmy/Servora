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

    // Account edit modal (platform-level fields; per-company access lives
    // in Settings > Users within each company)
    public bool    $showEdit      = false;
    public ?int    $editId        = null;
    public string  $e_name        = '';
    public string  $e_email       = '';
    public string  $e_designation = '';
    public string  $e_password    = '';
    public bool    $e_verified    = true;

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

    public function openEdit(int $userId): void
    {
        if (! Auth::user()->isSystemRole()) return;
        $user = User::find($userId);
        if (! $user) return;

        $this->editId        = $user->id;
        $this->e_name        = $user->name;
        $this->e_email       = $user->email;
        $this->e_designation = $user->designation ?? '';
        $this->e_password    = '';
        $this->e_verified    = $user->email_verified_at !== null;
        $this->showEdit      = true;
    }

    public function saveEdit(): void
    {
        if (! Auth::user()->isSystemRole() || ! $this->editId) return;
        $user = User::find($this->editId);
        if (! $user) return;

        $this->validate([
            'e_name'        => 'required|string|max:100',
            'e_email'       => ['required', 'email', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'e_designation' => 'nullable|string|max:100',
            'e_password'    => 'nullable|string|min:8',
        ], [], [
            'e_name' => 'name', 'e_email' => 'email',
            'e_designation' => 'designation', 'e_password' => 'password',
        ]);

        $data = [
            'name'        => $this->e_name,
            'email'       => $this->e_email,
            'designation' => $this->e_designation ?: null,
        ];
        if ($this->e_password !== '') {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($this->e_password);
        }
        $user->update($data);

        // email_verified_at is not mass-assignable — set it explicitly.
        // Keep the original timestamp when already verified; stamp now()
        // when newly verifying; null to revoke.
        $user->forceFill([
            'email_verified_at' => $this->e_verified ? ($user->email_verified_at ?? now()) : null,
        ])->save();

        Log::info('Admin edited user account', [
            'admin_id' => Auth::id(), 'target_id' => $user->id,
            'password_reset' => $this->e_password !== '',
        ]);

        $this->showEdit = false;
        $this->editId   = null;
        session()->flash('success', 'User "' . $user->name . '" updated.');
    }

    public function closeEdit(): void
    {
        $this->showEdit = false;
        $this->editId   = null;
        $this->resetValidation();
    }

    /**
     * Suspend / reinstate an account. Suspension logs the user out on their
     * next request (EnsureAccountActive middleware) and their sessions are
     * killed immediately; system-level accounts and self are exempt.
     */
    public function toggleSuspend(int $userId): void
    {
        $admin = Auth::user();
        if (! $admin->isSystemRole()) return;
        if ($userId === $admin->id) {
            session()->flash('error', 'You cannot suspend your own account.');
            return;
        }

        $user = User::find($userId);
        if (! $user) return;
        if ($user->hasGlobalRole(['Super Admin', 'System Admin'])) {
            session()->flash('error', 'System-level accounts cannot be suspended.');
            return;
        }

        if ($user->suspended_at) {
            $user->forceFill(['suspended_at' => null])->save();
            Log::info('Admin unsuspended user', ['admin_id' => $admin->id, 'target_id' => $user->id]);
            session()->flash('success', 'User "' . $user->name . '" reinstated.');
        } else {
            $user->forceFill(['suspended_at' => now()])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Log::info('Admin suspended user', ['admin_id' => $admin->id, 'target_id' => $user->id]);
            session()->flash('success', 'User "' . $user->name . '" suspended — they have been logged out.');
        }
    }

    /**
     * Delete an account entirely, across every company — memberships, outlet
     * and kitchen assignments, role/permission rows and sessions included.
     * Self and system-level accounts can never be deleted here.
     */
    public function deleteUser(int $userId): void
    {
        $admin = Auth::user();
        if (! $admin->isSystemRole()) return;
        if ($userId === $admin->id) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        $user = User::find($userId);
        if (! $user) return;
        if ($user->hasGlobalRole(['Super Admin', 'System Admin'])) {
            session()->flash('error', 'System-level accounts cannot be deleted here.');
            return;
        }

        $user->outlets()->detach();
        $user->companies()->detach();
        DB::table('kitchen_users')->where('user_id', $user->id)->delete();
        DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $user->id)->delete();
        DB::table('model_has_permissions')->where('model_type', User::class)->where('model_id', $user->id)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        Log::info('Admin deleted user account', [
            'admin_id' => $admin->id, 'target_id' => $user->id, 'target_email' => $user->email,
        ]);

        $name = $user->name;
        $user->delete();
        session()->flash('success', 'User "' . $name . '" deleted (all companies).');
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

        // Last activity per visible user: last_active_at heartbeat first,
        // DB sessions as legacy fallback (prod sessions are on Redis).
        $lastActive = DB::table('sessions')
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(last_activity) as la')
            ->pluck('la', 'user_id')
            ->map(fn ($ts) => \Carbon\Carbon::createFromTimestamp($ts));
        foreach ($users->items() as $u) {
            if ($u->last_active_at && (! isset($lastActive[$u->id]) || $u->last_active_at->gt($lastActive[$u->id]))) {
                $lastActive[$u->id] = $u->last_active_at;
            }
        }

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
