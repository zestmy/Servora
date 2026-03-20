<?php

namespace App\Livewire\Lms;

use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SopView extends Component
{
    public int $recipeId;
    public Recipe $recipe;

    public function mount(int $id): void
    {
        $this->recipeId = $id;
        $this->recipe = Recipe::where('company_id', Auth::guard('lms')->user()->company_id)
            ->where('is_active', true)
            ->with(['steps', 'images', 'lines.ingredient', 'lines.uom', 'yieldUom'])
            ->findOrFail($id);
    }

    public function render()
    {
        $dineInImages   = $this->recipe->images->where('type', 'dine_in')->values();
        $takeawayImages = $this->recipe->images->where('type', 'takeaway')->values();

        return view('livewire.lms.sop-view', compact('dineInImages', 'takeawayImages'))
            ->layout('layouts.lms', ['title' => $this->recipe->name]);
    }

    public function parseVideoEmbed(?string $url): ?string
    {
        if (! $url) return null;

        // YouTube — use privacy-enhanced embed (no cookies, no branding)
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
        }

        // Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
        }

        return null;
    }
}
