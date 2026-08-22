<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-page-header title="Documentation" eyebrow="Admin"
                   subtitle="The help centre at /help. Everything here is written and edited in the app — no deploy needed.">
        <x-slot:actions>
            <a href="{{ route('help.index') }}" target="_blank" rel="noopener" class="btn-secondary">
                View help centre
            </a>
            <button wire:click="createCategory" class="btn-secondary">+ Section</button>
            <a href="{{ route('admin.docs.create', ['category' => $openCategory]) }}" wire:navigate class="btn-primary">
                + Article
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="toolbar mb-4">
        <div class="flex-1 min-w-[14rem]">
            <input wire:model.live.debounce.300ms="search" type="text" class="input"
                   placeholder="Search every article by title, summary or keyword…" aria-label="Search articles" />
        </div>
        @if ($search)
            <span class="text-xs text-gray-600">Searching the whole manual</span>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-[20rem_1fr]">
        {{-- Sections --}}
        <div class="card p-2">
            @forelse ($categories as $category)
                <div wire:key="cat-{{ $category->id }}"
                     class="rounded-control px-3 py-2.5 transition
                            {{ $openCategory === $category->id ? 'bg-brand-50' : 'hover:bg-gray-50' }}">
                    <div class="flex items-start gap-2">
                        <button wire:click="toggleCategory({{ $category->id }})"
                                class="flex min-w-0 flex-1 items-start gap-2 text-left">
                            <x-icon :name="$category->icon ?: 'book-open'" size="h-4 w-4"
                                    class="mt-0.5 flex-none {{ $openCategory === $category->id ? 'text-brand-700' : 'text-gray-500' }}" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-gray-900">{{ $category->title }}</span>
                                <span class="block text-[11px] text-gray-600">
                                    {{ $category->articles_count }} {{ Str::plural('article', $category->articles_count) }}
                                    · /help/{{ $category->slug }}
                                </span>
                                @unless ($category->isPublic())
                                    {{-- Only shown when it is NOT public: a badge on
                                         every row would be noise, and public is the
                                         case that needs no explaining. --}}
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-warning-50 px-1.5 py-0.5 text-[10px] font-medium text-warning-800">
                                        <x-icon name="shield" size="h-2.5 w-2.5" />
                                        {{ $category->visibilityLabel() }}
                                    </span>
                                @endunless
                            </span>
                        </button>

                        <div class="flex flex-none items-center gap-0.5">
                            <button wire:click="toggleCategoryPublished({{ $category->id }})"
                                    title="{{ $category->is_published ? 'Published — click to hide' : 'Hidden — click to publish' }}"
                                    class="px-1.5 py-0.5 rounded-full text-[10px] font-medium
                                           {{ $category->is_published ? 'bg-success-100 text-success-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $category->is_published ? 'Live' : 'Hidden' }}
                            </button>
                            <button wire:click="editCategory({{ $category->id }})" class="icon-btn" aria-label="Edit section">
                                <x-icon name="cog" size="h-3.5 w-3.5" />
                            </button>
                            <button wire:click="deleteCategory({{ $category->id }})"
                                    data-confirm-delete="Delete “{{ $category->title }}” and its {{ $category->articles_count }} article(s)? This cannot be undone."
                                    class="icon-btn icon-btn-danger" aria-label="Delete section">
                                <x-icon name="trash" size="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state py-10">
                    <x-icon name="book-open" size="h-7 w-7" class="text-gray-500" />
                    <p class="text-sm font-medium text-gray-700">No sections yet</p>
                    <button wire:click="createCategory" class="btn-secondary">Create the first section</button>
                </div>
            @endforelse
        </div>

        {{-- Articles --}}
        <div class="card overflow-hidden">
            <table class="table-surface min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Article</th>
                        @if ($search)
                            <th class="px-4 py-3 text-left">Section</th>
                        @endif
                        <th class="px-4 py-3 text-center">Views</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($articles as $article)
                        <tr wire:key="art-{{ $article->id }}" class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.docs.edit', $article->id) }}" wire:navigate
                                   class="font-medium text-gray-900 hover:text-brand-700">{{ $article->title }}</a>
                                <p class="text-[11px] text-gray-600 font-mono">/help/{{ $article->category?->slug }}/{{ $article->slug }}</p>
                            </td>
                            @if ($search)
                                <td class="px-4 py-3 text-xs text-gray-600">{{ $article->category?->title }}</td>
                            @endif
                            <td class="px-4 py-3 text-center text-xs tabular-nums text-gray-600">{{ number_format($article->view_count) }}</td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleArticlePublished({{ $article->id }})"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium
                                               {{ $article->is_published ? 'bg-success-100 text-success-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $article->is_published ? 'Published' : 'Draft' }}
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-0.5">
                                    @unless ($search)
                                        <button wire:click="moveArticle({{ $article->id }}, 'up')" class="icon-btn" aria-label="Move up">
                                            <x-icon name="chevron-down" size="h-3.5 w-3.5" class="rotate-180" />
                                        </button>
                                        <button wire:click="moveArticle({{ $article->id }}, 'down')" class="icon-btn" aria-label="Move down">
                                            <x-icon name="chevron-down" size="h-3.5 w-3.5" />
                                        </button>
                                    @endunless
                                    @if ($article->is_published)
                                        <a href="{{ $article->url() }}" target="_blank" rel="noopener" class="icon-btn" aria-label="View live">
                                            <x-icon name="arrow-right" size="h-3.5 w-3.5" />
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.docs.edit', $article->id) }}" wire:navigate class="icon-btn" aria-label="Edit">
                                        <x-icon name="clipboard" size="h-3.5 w-3.5" />
                                    </a>
                                    <button wire:click="deleteArticle({{ $article->id }})"
                                            data-confirm-delete="Delete “{{ $article->title }}”?"
                                            class="icon-btn icon-btn-danger" aria-label="Delete">
                                        <x-icon name="trash" size="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $search ? 5 : 4 }}" class="px-4 py-12">
                                <div class="empty-state">
                                    <x-icon name="document" size="h-8 w-8" class="text-gray-500" />
                                    <p class="font-medium text-gray-700">
                                        {{ $search ? 'No article matches that' : 'No articles in this section yet' }}
                                    </p>
                                    @unless ($search)
                                        <a href="{{ route('admin.docs.create', ['category' => $openCategory]) }}"
                                           wire:navigate class="btn-secondary">Write the first one</a>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Section editor --}}
    @if ($showCategory)
        @teleport('body')
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-gray-900/50" wire:click="closeCategory"></div>
            <div class="relative bg-white rounded-panel shadow-e4 w-full max-w-lg p-6">
                <h2 class="text-base font-bold text-gray-900 mb-4">
                    {{ $categoryId ? 'Edit section' : 'New section' }}
                </h2>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="cat_title" value="Title *" />
                            <x-text-input id="cat_title" wire:model.live.debounce.300ms="cat_title" type="text"
                                          class="mt-1 block w-full" placeholder="Purchasing" />
                            <x-input-error :messages="$errors->get('cat_title')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="cat_slug" value="Slug *" />
                            <div class="mt-1 flex items-center">
                                <span class="text-xs text-gray-600 mr-1">/help/</span>
                                <x-text-input id="cat_slug" wire:model="cat_slug" type="text" class="flex-1" />
                            </div>
                            <x-input-error :messages="$errors->get('cat_slug')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="cat_summary" value="Summary" />
                        <textarea id="cat_summary" wire:model="cat_summary" rows="2" class="input mt-1"
                                  placeholder="One line, shown on the section tile."></textarea>
                        <x-input-error :messages="$errors->get('cat_summary')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label value="Icon" />
                        <p class="help mb-2">Shown on the tile and in the sidebar. A section with no icon renders a blank square.</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($iconChoices as $icon)
                                <button type="button" wire:click="$set('cat_icon', '{{ $icon }}')"
                                        title="{{ $icon }}"
                                        class="flex h-9 w-9 items-center justify-center rounded-control border transition
                                               {{ $cat_icon === $icon
                                                   ? 'border-brand-500 bg-brand-50 text-brand-700'
                                                   : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                                    <x-icon :name="$icon" size="h-4 w-4" />
                                </button>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('cat_icon')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="cat_visibility" value="Who can read this section" />
                        <select id="cat_visibility" wire:model="cat_visibility" class="input mt-1">
                            @foreach ($visibilities as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="help mt-1">
                            Separate from Published. Published asks whether the section is finished;
                            this asks who it is for. A section somebody may not read is a 404 for
                            them — it never appears in the tiles, in search, or at its own URL.
                        </p>
                        <x-input-error :messages="$errors->get('cat_visibility')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 items-end">
                        <div>
                            <x-input-label for="cat_sort_order" value="Sort order" />
                            <x-text-input id="cat_sort_order" wire:model="cat_sort_order" type="number" min="0"
                                          class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('cat_sort_order')" class="mt-1" />
                        </div>
                        <label class="inline-flex items-center gap-2 cursor-pointer pb-2">
                            <input type="checkbox" wire:model="cat_is_published"
                                   class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" />
                            <span class="text-sm font-medium text-gray-700">Published</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button wire:click="closeCategory" class="btn-secondary">Cancel</button>
                    <button wire:click="saveCategory" class="btn-primary">Save section</button>
                </div>
            </div>
        </div>
        @endteleport
    @endif
</div>
