<?php

namespace App\Livewire\Lms;

use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SopView extends Component
{
    public int $recipeId;
    public Recipe $recipe;
    public string $backSearch = '';
    public string $backCategoryFilter = '';

    public function mount(int $id): void
    {
        $user = Auth::guard('lms')->user();
        $this->recipeId = $id;

        // Remember the dashboard filter the user came from, so "Back to all
        // SOPs" returns to the same filtered list.
        $this->backSearch         = (string) request()->query('search', '');
        $this->backCategoryFilter = (string) request()->query('categoryFilter', '');

        $this->recipe = Recipe::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('exclude_from_lms', false)
            ->visibleToOutlets($user->accessibleOutletIds())
            ->with([
                'steps', 'images', 'lines.uom', 'yieldUom',
                'lines.ingredient.recipeUom', 'lines.ingredient.secondaryRecipeUom', 'lines.ingredient.uomConversions',
            ])
            ->findOrFail($id);
    }

    public function render()
    {
        // Prep items store their photos as type 'presentation'; show them in
        // the same slot as recipe dine-in photos (a recipe never has both).
        $dineInImages   = $this->recipe->images->whereIn('type', ['dine_in', 'presentation'])->values();
        $takeawayImages = $this->recipe->images->where('type', 'takeaway')->values();

        // Latest update activity — same data as the SOP PDFs' "Latest 5 Update
        // Activity" section, so changes are visible on screen when the SOP opens.
        $recentActivity = \App\Models\AuditLog::with('user:id,name')
            ->select(['id', 'user_id', 'user_name', 'event', 'auditable_id', 'old_values', 'new_values', 'created_at'])
            ->where('auditable_type', Recipe::class)
            ->where('auditable_id', $this->recipe->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('livewire.lms.sop-view', compact('dineInImages', 'takeawayImages', 'recentActivity'))
            ->layout('layouts.lms', ['title' => $this->recipe->name]);
    }

    public function getVideoData(?string $url): ?array
    {
        if (! $url) return null;

        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return ['type' => 'youtube', 'id' => $m[1]];
        }

        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return ['type' => 'vimeo', 'id' => $m[1]];
        }

        return null;
    }
}
