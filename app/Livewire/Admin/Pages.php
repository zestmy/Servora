<?php

namespace App\Livewire\Admin;

use App\Models\Page;
use Illuminate\Support\Str;
use Livewire\Component;

class Pages extends Component
{
    public bool $showEditor = false;
    public ?int $editingId = null;

    public string $title           = '';
    public string $slug            = '';
    public string $external_url    = '';
    public bool   $open_in_new_tab = false;
    public string $content         = '';
    public bool   $is_published    = false;
    public string $menu_placement  = '';
    public string $sort_order      = '0';

    protected function rules(): array
    {
        $uniqueSlug = 'unique:pages,slug' . ($this->editingId ? ',' . $this->editingId : '');

        return [
            'title'          => 'required|string|max:200',
            'slug'           => ['required', 'string', 'max:200', 'alpha_dash', $uniqueSlug],
            'external_url'   => 'nullable|url|max:500',
            'content'        => 'nullable|string',
            'menu_placement' => 'nullable|in:header,footer,both',
            'sort_order'     => 'required|integer|min:0',
        ];
    }

    public function updatedTitle(): void
    {
        if (!$this->editingId) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showEditor = true;
    }

    public function edit(int $id): void
    {
        $page = Page::findOrFail($id);
        $this->editingId       = $page->id;
        $this->title           = $page->title;
        $this->slug            = $page->slug;
        $this->external_url    = $page->external_url ?? '';
        $this->open_in_new_tab = $page->open_in_new_tab;
        $this->content         = $page->content ?? '';
        $this->is_published    = $page->is_published;
        $this->menu_placement  = $page->menu_placement ?? '';
        $this->sort_order      = (string) $page->sort_order;
        $this->showEditor = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'title'           => $this->title,
            'slug'            => $this->slug,
            'external_url'    => $this->external_url ?: null,
            'open_in_new_tab' => $this->open_in_new_tab,
            'content'         => $this->external_url ? null : ($this->content ?: null),
            'is_published'    => $this->is_published,
            'menu_placement'  => $this->menu_placement ?: null,
            'sort_order'      => (int) $this->sort_order,
        ];

        if ($this->editingId) {
            Page::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Page updated.');
        } else {
            Page::create($data);
            session()->flash('success', 'Page created.');
        }

        $this->showEditor = false;
        $this->resetForm();
    }

    public function togglePublish(int $id): void
    {
        $page = Page::findOrFail($id);
        $page->update(['is_published' => !$page->is_published]);
    }

    public function delete(int $id): void
    {
        Page::findOrFail($id)->delete();
        session()->flash('success', 'Page deleted.');
    }

    public function cancel(): void
    {
        $this->showEditor = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->slug = '';
        $this->external_url = '';
        $this->open_in_new_tab = false;
        $this->content = '';
        $this->is_published = false;
        $this->menu_placement = '';
        $this->sort_order = '0';
        $this->resetValidation();
    }

    public function render()
    {
        $pages = Page::orderBy('sort_order')->orderBy('title')->get();

        return view('livewire.admin.pages', compact('pages'))
            ->layout('layouts.app', ['title' => 'Pages']);
    }
}
