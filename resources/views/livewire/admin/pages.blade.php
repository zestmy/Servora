<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
    @endif

    @if (!$showEditor)
        {{-- List View --}}
        <div class="flex items-center gap-3 mb-4">
            <div class="flex-1">
                <h1 class="text-lg font-bold text-gray-800">Pages</h1>
                <p class="text-xs text-gray-400 mt-0.5">Manage About, Privacy Policy, Terms of Use, and other content pages.</p>
            </div>
            <button wire:click="create" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">+ New Page</button>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Page</th>
                        <th class="px-4 py-3 text-left">Slug</th>
                        <th class="px-4 py-3 text-center">Menu</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($pages as $page)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">{{ $page->title }}</p>
                                @if ($page->isExternal())
                                    <p class="text-xs text-blue-500 truncate max-w-xs">{{ $page->external_url }}</p>
                                @else
                                    <p class="text-xs text-gray-400">{{ Str::limit(strip_tags($page->content), 60) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 font-mono">
                                @if ($page->isExternal())
                                    <span class="text-blue-500">External</span>
                                @else
                                    /page/{{ $page->slug }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($page->menu_placement)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-indigo-50 text-indigo-700">{{ ucfirst($page->menu_placement) }}</span>
                                @else
                                    <span class="text-xs text-gray-300">None</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="togglePublish({{ $page->id }})"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium {{ $page->is_published ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $page->is_published ? 'Published' : 'Draft' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if ($page->is_published)
                                        <a href="{{ $page->url() }}" target="_blank" title="View" class="text-gray-400 hover:text-gray-600 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                    @endif
                                    <button wire:click="edit({{ $page->id }})" title="Edit" class="text-indigo-500 hover:text-indigo-700 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button wire:click="delete({{ $page->id }})" wire:confirm="Delete '{{ $page->title }}'?" title="Delete" class="text-red-400 hover:text-red-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                                <p class="font-medium">No pages yet</p>
                                <p class="text-xs mt-1">Create pages like About, Privacy Policy, and Terms of Use.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Site Settings --}}
        <div class="mt-8">
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Site Settings</h2>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-input-label for="footer_copy" value="Footer Copyright Text" />
                        <x-text-input id="footer_copy" wire:model="footer_copyright" type="text" class="mt-1 block w-full"
                                      placeholder="&copy; 2026 Servora. All rights reserved." />
                        <p class="text-xs text-gray-400 mt-1">Supports HTML. Use <code>&amp;copy;</code> for the copyright symbol.</p>
                    </div>
                    <button wire:click="saveSiteSettings"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition flex-shrink-0">
                        Save
                    </button>
                </div>
            </div>
        </div>
    @else
        {{-- Editor View --}}
        <div class="flex items-center gap-3 mb-4">
            <button wire:click="cancel" class="text-gray-400 hover:text-gray-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>
            <div>
                <p class="text-xs text-gray-400"><a href="#" wire:click.prevent="cancel" class="hover:underline">Pages</a> / {{ $editingId ? 'Edit' : 'Create' }}</p>
            </div>
        </div>

        <form wire:submit="save" class="max-w-4xl space-y-6">
            {{-- Title & Slug --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <x-input-label for="page_title" value="Page Title *" />
                        <x-text-input id="page_title" wire:model.live.debounce.300ms="title" type="text" class="mt-1 block w-full" placeholder="e.g. Privacy Policy" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="page_slug" value="URL Slug *" />
                        <div class="mt-1 flex items-center">
                            <span class="text-sm text-gray-400 mr-1">/page/</span>
                            <x-text-input id="page_slug" wire:model="slug" type="text" class="flex-1" />
                        </div>
                        <x-input-error :messages="$errors->get('slug')" class="mt-1" />
                    </div>
                </div>

                {{-- External URL --}}
                <div class="mb-4">
                    <x-input-label for="page_url" value="External URL (optional)" />
                    <x-text-input id="page_url" wire:model="external_url" type="url" class="mt-1 block w-full" placeholder="https://example.com — leave empty for a content page" />
                    <x-input-error :messages="$errors->get('external_url')" class="mt-1" />
                    <p class="text-xs text-gray-400 mt-1">If set, this menu item links to an external URL instead of showing page content.</p>
                </div>

                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <x-input-label for="page_menu" value="Show in Menu" />
                        <select id="page_menu" wire:model="menu_placement"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">None</option>
                            <option value="header">Header only</option>
                            <option value="footer">Footer only</option>
                            <option value="both">Header & Footer</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="page_sort" value="Sort Order" />
                        <x-text-input id="page_sort" wire:model="sort_order" type="number" min="0" class="mt-1 block w-full" />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="open_in_new_tab"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700 font-medium">New Tab</span>
                        </label>
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_published"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700 font-medium">Published</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Content Editor (hidden for external links) --}}
            @if (!$external_url)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <x-input-label value="Page Content" />
                <p class="text-xs text-gray-400 mb-3">Supports HTML. Use headings, paragraphs, lists, and links.</p>
                <div x-data="{
                    tab: 'write',
                    content: @entangle('content'),
                }">
                    {{-- Tabs --}}
                    <div class="flex gap-2 mb-3 border-b border-gray-200">
                        <button type="button" @click="tab = 'write'"
                                :class="tab === 'write' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'"
                                class="pb-2 text-sm font-medium border-b-2 transition">Write</button>
                        <button type="button" @click="tab = 'preview'"
                                :class="tab === 'preview' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'"
                                class="pb-2 text-sm font-medium border-b-2 transition">Preview</button>
                    </div>

                    {{-- Toolbar --}}
                    <div x-show="tab === 'write'" class="flex items-center gap-1 mb-2 text-xs">
                        <button type="button" @click="content = content + '<h2>Heading</h2>\n'" class="px-2 py-1 bg-gray-100 rounded hover:bg-gray-200 transition font-bold">H2</button>
                        <button type="button" @click="content = content + '<h3>Subheading</h3>\n'" class="px-2 py-1 bg-gray-100 rounded hover:bg-gray-200 transition font-bold">H3</button>
                        <button type="button" @click="content = content + '<p></p>\n'" class="px-2 py-1 bg-gray-100 rounded hover:bg-gray-200 transition">P</button>
                        <button type="button" @click="content = content + '<ul>\n<li></li>\n</ul>\n'" class="px-2 py-1 bg-gray-100 rounded hover:bg-gray-200 transition">UL</button>
                        <button type="button" @click="content = content + '<strong></strong>'" class="px-2 py-1 bg-gray-100 rounded hover:bg-gray-200 transition font-bold">B</button>
                        <button type="button" @click="content = content + '<a href=\"#\"></a>'" class="px-2 py-1 bg-gray-100 rounded hover:bg-gray-200 transition text-blue-600 underline">Link</button>
                        <button type="button" @click="content = content + '<hr>\n'" class="px-2 py-1 bg-gray-100 rounded hover:bg-gray-200 transition">HR</button>
                    </div>

                    {{-- Write --}}
                    <textarea x-show="tab === 'write'"
                              x-model="content"
                              wire:model.lazy="content"
                              rows="20"
                              class="block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="<h2>Privacy Policy</h2>
<p>Your privacy is important to us...</p>"></textarea>

                    {{-- Preview --}}
                    <div x-show="tab === 'preview'" x-cloak
                         class="min-h-[20rem] border border-gray-200 rounded-md p-6 bg-gray-50 prose prose-sm max-w-none"
                         x-html="content || '<p class=\'text-gray-400\'>Nothing to preview yet.</p>'">
                    </div>
                </div>
                <x-input-error :messages="$errors->get('content')" class="mt-1" />
            </div>
            @else
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700">
                    This is an external link — it points to <strong>{{ $external_url }}</strong>. No page content needed.
                </div>
            @endif

            {{-- Submit --}}
            <div class="flex items-center justify-end gap-3">
                <button type="button" wire:click="cancel" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    {{ $editingId ? 'Update Page' : 'Create Page' }}
                </button>
            </div>
        </form>
    @endif
</div>
