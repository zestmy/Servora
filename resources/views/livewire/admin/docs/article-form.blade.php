<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-page-header :title="$articleId ? 'Edit article' : 'New article'" eyebrow="Admin · Documentation">
        <x-slot:actions>
            @if ($articleId && $is_published)
                <a href="{{ route('help.article', [\App\Models\DocCategory::find($doc_category_id)?->slug ?? 'general', $slug]) }}"
                   target="_blank" rel="noopener" class="btn-secondary">View live</a>
            @endif
            <a href="{{ route('admin.docs.index') }}" wire:navigate class="btn-secondary">Back</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem] items-start">
        <form wire:submit="save" class="space-y-6 min-w-0">
            <div class="card p-6">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-input-label for="title" value="Title *" />
                        <x-text-input id="title" wire:model.live.debounce.300ms="title" type="text"
                                      class="mt-1 block w-full" placeholder="Raise a purchase order" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="doc_category_id" value="Section *" />
                        <select id="doc_category_id" wire:model="doc_category_id" class="input mt-1">
                            <option value="">Select a section…</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->title }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('doc_category_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="slug" value="Slug *" />
                        <x-text-input id="slug" wire:model="slug" type="text" class="mt-1 block w-full" />
                        <p class="help mt-1">Unique across the whole manual, so an article keeps its link when it moves section.</p>
                        <x-input-error :messages="$errors->get('slug')" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <x-input-label for="excerpt" value="Summary" />
                        <textarea id="excerpt" wire:model="excerpt" rows="2" class="input mt-1"
                                  placeholder="One or two lines. Shown in search results and section lists."></textarea>
                        <x-input-error :messages="$errors->get('excerpt')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="keywords" value="Search keywords" />
                        <x-text-input id="keywords" wire:model="keywords" type="text" class="mt-1 block w-full"
                                      placeholder="PO, order, supplier order" />
                        <p class="help mt-1">The words a reader types when the article calls it something else.</p>
                        <x-input-error :messages="$errors->get('keywords')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 items-end">
                        <div>
                            <x-input-label for="sort_order" value="Sort order" />
                            <x-text-input id="sort_order" wire:model="sort_order" type="number" min="0"
                                          class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
                        </div>
                        <label class="inline-flex items-center gap-2 cursor-pointer pb-2">
                            <input type="checkbox" wire:model="is_published"
                                   class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500" />
                            <span class="text-sm font-medium text-gray-700">Published</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Body. Write / preview, the same idiom as Admin\Pages — the
                 people writing this are the people writing those. --}}
            <div class="card p-6">
                <x-input-label value="Body" />
                <p class="help mb-3">
                    HTML. Use <code>&lt;h2&gt;</code> for steps, <code>&lt;ol&gt;</code> for sequences, and the
                    figure buttons on the right to place screenshots.
                </p>

                <div x-data="{ tab: 'write' }">
                    <div class="flex gap-3 mb-3 border-b border-gray-200">
                        <button type="button" @click="tab = 'write'"
                                :class="tab === 'write' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-600'"
                                class="pb-2 text-sm font-medium border-b-2 transition">Write</button>
                        <button type="button" @click="tab = 'preview'"
                                :class="tab === 'preview' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-600'"
                                class="pb-2 text-sm font-medium border-b-2 transition">Preview</button>
                    </div>

                    <div x-show="tab === 'write'">
                        <div class="flex flex-wrap items-center gap-1 mb-2 text-xs">
                            @foreach ([
                                'H2'     => '<h2>Heading</h2>',
                                'H3'     => '<h3>Subheading</h3>',
                                'Para'   => '<p></p>',
                                'Steps'  => "<ol>\n  <li></li>\n  <li></li>\n</ol>",
                                'Bullets'=> "<ul>\n  <li></li>\n</ul>",
                                'Note'   => '<blockquote><p></p></blockquote>',
                                'Table'  => "<table>\n  <thead><tr><th></th><th></th></tr></thead>\n  <tbody><tr><td></td><td></td></tr></tbody>\n</table>",
                            ] as $label => $snippet)
                                {{-- Alpine, not wire:click: $set takes a value,
                                     not a JS expression, so string concatenation
                                     has to happen on the client. --}}
                                <button type="button"
                                        @click="$wire.body = ($wire.body || '').trimEnd() + '\n\n' + @js($snippet) + '\n'"
                                        class="px-2 py-1 bg-gray-100 rounded-control hover:bg-gray-200 transition">{{ $label }}</button>
                            @endforeach
                        </div>

                        <textarea wire:model.live.debounce.600ms="body" rows="24"
                                  class="input font-mono text-xs leading-relaxed"
                                  aria-label="Article body"></textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-1" />
                    </div>

                    <div x-show="tab === 'preview'" x-cloak
                         class="doc-prose prose prose-sm max-w-none rounded-surface border border-gray-200 p-5
                                prose-a:text-brand-700 prose-img:rounded-surface prose-img:border prose-img:border-gray-200
                                prose-figcaption:text-xs prose-figcaption:text-gray-600 prose-figcaption:text-center">
                        {!! $body !!}
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('admin.docs.index') }}" wire:navigate class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">{{ $articleId ? 'Save article' : 'Create article' }}</button>
            </div>
        </form>

        {{-- Figures --}}
        <div class="card p-5">
            <h2 class="text-sm font-semibold text-gray-800">Figures</h2>
            <p class="help mt-1 mb-4">
                Screenshots and diagrams for this article. Adding one appends it to the end of the
                body — move it to the step it illustrates.
            </p>

            @if (! $articleId)
                <div class="alert-info text-xs">
                    <span>Save the article first. A figure needs an article to belong to.</span>
                </div>
            @else
                <div class="space-y-3">
                    <div>
                        <x-input-label for="upload" value="Image file" />
                        <input id="upload" type="file" wire:model="upload" accept="image/*"
                               class="mt-1 block w-full text-xs text-gray-700 file:mr-3 file:rounded-control
                                      file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-xs
                                      file:font-medium file:text-brand-700 hover:file:bg-brand-100" />
                        <p class="help mt-1">JPG, PNG, GIF, WebP or HEIC. Up to {{ round(\App\Livewire\Admin\Docs\ArticleForm::MAX_IMAGE_KB / 1024) }} MB.</p>
                        <x-input-error :messages="$errors->get('upload')" class="mt-1" />
                        <div wire:loading wire:target="upload" class="mt-1 text-xs text-gray-600">Uploading…</div>
                    </div>

                    <div>
                        <x-input-label for="upload_alt" value="Alt text *" />
                        <x-text-input id="upload_alt" wire:model="upload_alt" type="text" class="mt-1 block w-full text-sm"
                                      placeholder="The purchase order form with three lines filled in" />
                        <p class="help mt-1">Read aloud to anyone who cannot see the image. Describe what it shows, not "screenshot".</p>
                        <x-input-error :messages="$errors->get('upload_alt')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="upload_caption" value="Caption" />
                        <x-text-input id="upload_caption" wire:model="upload_caption" type="text"
                                      class="mt-1 block w-full text-sm" placeholder="Printed under the figure." />
                        <x-input-error :messages="$errors->get('upload_caption')" class="mt-1" />
                    </div>

                    <button type="button" wire:click="addImage" class="btn-secondary w-full">Add figure</button>
                </div>

                @if ($images->isNotEmpty())
                    <div class="mt-6 space-y-3 border-t border-gray-200 pt-4">
                        @foreach ($images as $image)
                            <div wire:key="img-{{ $image->id }}" class="flex gap-3">
                                <img src="{{ $image->url() }}" alt="{{ $image->alt }}"
                                     class="h-14 w-20 flex-none rounded-control border border-gray-200 object-cover" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-medium text-gray-800">{{ $image->alt }}</p>
                                    @if ($image->caption)
                                        <p class="truncate text-[11px] text-gray-600">{{ $image->caption }}</p>
                                    @endif
                                    <div class="mt-1 flex items-center gap-2">
                                        <button type="button" wire:click="insertImage({{ $image->id }})"
                                                class="text-[11px] font-medium text-brand-700 hover:text-brand-800">Insert</button>
                                        <button type="button" wire:click="deleteImage({{ $image->id }})"
                                                data-confirm-delete="Delete this figure? Remove it from the body too."
                                                class="text-[11px] font-medium text-danger-600 hover:text-danger-700">Delete</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
