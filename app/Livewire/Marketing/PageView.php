<?php

namespace App\Livewire\Marketing;

use App\Models\Page;
use Livewire\Component;

class PageView extends Component
{
    public Page $page;

    public function mount(string $slug): void
    {
        $this->page = Page::where('slug', $slug)->published()->firstOrFail();
    }

    public function render()
    {
        return view('livewire.marketing.page-view')
            ->layout('layouts.marketing', ['title' => $this->page->title]);
    }
}
