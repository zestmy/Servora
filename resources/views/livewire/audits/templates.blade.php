<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-page-header title="Audit Forms" eyebrow="Outlet Audits"
                   subtitle="The checklists an audit is conducted against. An audit copies its form when it starts, so editing a form never changes a score already given.">
        <x-slot:actions>
            @unless ($hasRose)
                <button wire:click="installRose" wire:loading.attr="disabled" class="btn-secondary">
                    <x-icon name="sparkles" size="h-4 w-4" />
                    <span wire:loading.remove wire:target="installRose">Install ROSE starter</span>
                    <span wire:loading wire:target="installRose">Installing…</span>
                </button>
            @endunless
            <button wire:click="openCreate" class="btn-primary">+ New form</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[720px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Form</th>
                        <th class="px-4 py-3 text-left w-40">Second language</th>
                        <th class="px-4 py-3 text-right w-24">Sections</th>
                        <th class="px-4 py-3 text-right w-24">Points</th>
                        <th class="px-4 py-3 text-right w-24">Audits</th>
                        <th class="px-4 py-3 text-left w-28">Status</th>
                        <th class="px-4 py-3 w-40"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($templates as $t)
                        <tr wire:key="tpl-{{ $t->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('audits.templates.edit', $t->id) }}" wire:navigate class="font-medium text-gray-900 hover:text-brand-700">
                                    @if ($t->code)<span class="badge-brand mr-1.5">{{ $t->code }}</span>@endif{{ $t->name }}
                                </a>
                                @if ($t->description)
                                    <p class="mt-0.5 line-clamp-1 text-xs text-gray-600">{{ $t->description }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $t->alt_language ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $t->sections_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $t->points }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $t->audits_count }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ $t->is_active ? 'badge-success' : 'badge-neutral' }}">{{ $t->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="toggleActive({{ $t->id }})" class="btn-ghost text-xs">
                                        {{ $t->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <button wire:click="duplicate({{ $t->id }})" class="icon-btn" title="Duplicate">
                                        <x-icon name="document" size="h-4 w-4" />
                                    </button>
                                    @canDo('audits.delete')
                                        <button wire:click="delete({{ $t->id }})" class="icon-btn icon-btn-danger" title="Delete"
                                                data-confirm-delete="Delete the form “{{ $t->name }}”. Audits already conducted from it keep their own copy and are not affected.">
                                            <x-icon name="trash" size="h-4 w-4" />
                                        </button>
                                    @endcanDo
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12">
                                <div class="empty-state">
                                    <p class="empty-title">No audit forms yet</p>
                                    <p class="empty-body">
                                        Install the ROSE starter — five sections, three hundred items, bilingual — and rename its product slots to your menu, or build a form from scratch.
                                    </p>
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <button wire:click="installRose" class="btn-primary">Install ROSE starter</button>
                                        <button wire:click="openCreate" class="btn-secondary">Blank form</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div x-data="{}" x-show="$wire.showCreate" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.set('showCreate', false)"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <form wire:submit="create" class="relative z-10 w-full max-w-md rounded-panel bg-white p-6 shadow-e3">
            <h3 class="text-base font-semibold text-gray-900">New audit form</h3>
            <div class="mt-4 space-y-4">
                <div>
                    <label class="label" for="tpl-name">Name</label>
                    <input id="tpl-name" type="text" wire:model="name" class="input" placeholder="Pre-opening inspection" />
                    @error('name') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="tpl-code">Short code</label>
                        <input id="tpl-code" type="text" wire:model="code" class="input" placeholder="POI" />
                        @error('code') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="tpl-alt">Second language</label>
                        <input id="tpl-alt" type="text" wire:model="altLanguage" class="input" placeholder="Bahasa Malaysia" />
                        <p class="help">Optional. Lets every item carry a second label.</p>
                    </div>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" wire:click="$set('showCreate', false)" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Create and build</button>
            </div>
        </form>
        </div>
        </div>
    </div>
</div>
