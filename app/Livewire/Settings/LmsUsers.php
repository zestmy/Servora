<?php

namespace App\Livewire\Settings;

use App\Models\LmsUser;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class LmsUsers extends Component
{
    use WithPagination;

    public string $statusFilter = 'pending';
    public string $search = '';

    // SOP access editing (per-outlet, incl. central kitchen outlets)
    public bool   $showAccessModal = false;
    public ?int   $accessUserId    = null;
    public string $accessUserName  = '';
    public array  $accessOutletIds = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    // ── SOP access editing ────────────────────────────────────────────────

    public function openAccess(int $id): void
    {
        $user = LmsUser::where('company_id', Auth::user()->company_id)->findOrFail($id);

        $this->accessUserId   = $user->id;
        $this->accessUserName = $user->name;

        // Current access — explicit rows, registration-outlet fallback, or
        // everything for legacy users registered without an outlet.
        $ids = $user->accessibleOutletIds();
        if (empty($ids)) {
            $ids = \App\Models\Outlet::where('company_id', $user->company_id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        }
        $this->accessOutletIds = array_map('strval', $ids);

        $this->showAccessModal = true;
    }

    public function selectAllAccessOutlets(): void
    {
        $this->accessOutletIds = \App\Models\Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function saveAccess(): void
    {
        if (! $this->accessUserId) return;

        $user = LmsUser::where('company_id', Auth::user()->company_id)->findOrFail($this->accessUserId);

        $validIds = \App\Models\Outlet::where('company_id', $user->company_id)
            ->whereIn('id', array_map('intval', $this->accessOutletIds))
            ->pluck('id')
            ->all();

        if (empty($validIds)) {
            session()->flash('error', 'Select at least one outlet — to block the user entirely, reject the account instead.');
            return;
        }

        $user->outlets()->sync($validIds);

        $this->showAccessModal = false;
        $this->accessUserId = null;
        session()->flash('success', "SOP access updated for {$user->name}.");
    }

    public function approve(int $id): void
    {
        $user = LmsUser::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $user->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        session()->flash('success', "{$user->name} has been approved.");
    }

    public function reject(int $id): void
    {
        $user = LmsUser::where('company_id', Auth::user()->company_id)->findOrFail($id);
        $user->update(['status' => 'rejected']);
        session()->flash('success', "{$user->name} has been rejected.");
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;
        $company   = Auth::user()->company;

        $users = LmsUser::where('company_id', $companyId)
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', "%{$this->search}%")
                   ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->with(['outlet', 'outlets', 'approver'])
            ->latest()
            ->paginate(20);

        // Stats
        $totalLmsUsers   = LmsUser::where('company_id', $companyId)->count();
        $pendingCount    = LmsUser::where('company_id', $companyId)->where('status', 'pending')->count();
        $approvedCount   = LmsUser::where('company_id', $companyId)->where('status', 'approved')->count();
        $rejectedCount   = LmsUser::where('company_id', $companyId)->where('status', 'rejected')->count();

        $totalSops       = Recipe::where('is_active', true)->where('is_prep', false)->where('exclude_from_lms', false)->count();
        $totalRecipes    = Recipe::where('is_active', true)->where('is_prep', false)->count();
        $recipesWithVideo = Recipe::where('is_active', true)->where('is_prep', false)->whereNotNull('video_url')->count();

        // SOP categories
        $sopCategories = Recipe::where('is_active', true)
            ->where('is_prep', false)
            ->where('exclude_from_lms', false)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();

        // Prep-item SOPs get their own export links.
        $hasPrepSops = Recipe::where('is_active', true)
            ->where('is_prep', true)
            ->where('exclude_from_lms', false)
            ->exists();

        // Menu categories (shared with recipes) holding at least one LMS-visible
        // prep item — each gets its own prep-SOP export link. Matched by name
        // (recipes.category stores the category name), preferring sub-categories
        // when the same name exists at both levels.
        $prepCategoryNames = Recipe::where('is_active', true)
            ->where('is_prep', true)
            ->where('exclude_from_lms', false)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->map(fn ($n) => mb_strtolower($n));

        $prepSopCategories = \App\Models\RecipeCategory::where('company_id', $companyId)
            ->with('parent')
            ->get()
            ->filter(fn ($c) => $prepCategoryNames->contains(mb_strtolower($c->name)))
            ->sortBy(fn ($c) => $c->parent_id === null ? 1 : 0)
            ->unique(fn ($c) => mb_strtolower($c->name))
            ->sortBy(fn ($c) => [
                $c->parent->sort_order ?? $c->sort_order,
                $c->parent->name ?? $c->name,
                $c->sort_order,
                $c->name,
            ])
            ->values();

        // Top-tier category groups (e.g. "All Food", "All Beverages") — root recipe
        // categories that contain at least one LMS recipe (themselves or via a child).
        $sopCategoryGroups = \App\Models\RecipeCategory::where('company_id', $companyId)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($root) => [
                'id'            => $root->id,
                'name'          => $root->name,
                'categoryNames' => collect([$root->name])->merge($root->children->pluck('name')),
            ])
            ->filter(fn ($g) => $g['categoryNames']->intersect($sopCategories)->isNotEmpty())
            ->values();

        $domain = config('app.domain');
        if ($domain && $company?->slug) {
            $lmsUrl = "https://{$company->slug}.{$domain}/lms/login";
            $lmsRegisterUrl = "https://{$company->slug}.{$domain}/lms/register";
        } elseif ($company?->slug) {
            $lmsUrl = url("/lms/{$company->slug}/login");
            $lmsRegisterUrl = url("/lms/{$company->slug}/register");
        } else {
            $lmsUrl = null;
            $lmsRegisterUrl = null;
        }

        // Outlets for the SOP-access editor; central kitchen outlets are
        // included (badged "CK") so CK SOP visibility can be granted/revoked.
        $accessOutlets = \App\Models\Outlet::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $centralKitchenOutletIds = \App\Models\CentralKitchen::whereNotNull('outlet_id')
            ->pluck('outlet_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return view('livewire.settings.lms-users', compact(
            'users', 'totalLmsUsers', 'pendingCount', 'approvedCount', 'rejectedCount',
            'totalSops', 'totalRecipes', 'recipesWithVideo', 'sopCategories', 'sopCategoryGroups', 'hasPrepSops', 'prepSopCategories',
            'lmsUrl', 'lmsRegisterUrl', 'company', 'accessOutlets', 'centralKitchenOutletIds'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Training Portal']);
    }
}
