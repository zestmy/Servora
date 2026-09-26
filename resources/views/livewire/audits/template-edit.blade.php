<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif

    <x-page-header :title="$template->name" eyebrow="Audit form"
                   subtitle="Every change saves as you make it. Version {{ $template->version }} — an audit started now copies the form as it stands.">
        <x-slot:actions>
            <a href="{{ route('audits.templates') }}" wire:navigate class="btn-secondary">All forms</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Form details --}}
    <div class="card mb-4 p-5" x-data="{ open: false }">
        <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 text-left">
            <x-card-title>Form details &amp; header fields</x-card-title>
            <x-icon name="chevron-down" size="h-5 w-5" class="text-gray-500 transition" x-bind:class="open ? 'rotate-180' : ''" />
        </button>

        <div x-show="open" x-collapse class="mt-4">
            <form wire:submit="saveHeader" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <label class="label" for="tpl-name">Name</label>
                        <input id="tpl-name" type="text" wire:model="name" class="input" />
                        @error('name') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="tpl-code">Short code</label>
                        <input id="tpl-code" type="text" wire:model="code" class="input" />
                    </div>
                    <div>
                        <label class="label" for="tpl-alt">Second language</label>
                        <input id="tpl-alt" type="text" wire:model="altLanguage" class="input" placeholder="Bahasa Malaysia" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <label class="label" for="tpl-desc">Description</label>
                        <textarea id="tpl-desc" wire:model="description" rows="2" class="input"></textarea>
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="requiresAcknowledgement" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    The outlet acknowledges the audit with a name, position and signature
                </label>

                <div>
                    <div class="flex items-center justify-between">
                        <span class="label">Header fields</span>
                        <button type="button" wire:click="addHeaderField" class="btn-ghost text-xs">+ Field</button>
                    </div>
                    <p class="help mb-2">Filled in at the top of every audit — shift officer on duty, headcount, area manager. “Outlet employee” offers the audited outlet's staff; “Appointed auditor” offers the company's auditor list from <a href="{{ route('audits.auditors') }}" wire:navigate class="text-brand-700 underline">Settings ▸ Appointed Auditors</a>.</p>

                    <div class="space-y-2">
                        @foreach ($headerFields as $i => $field)
                            <div wire:key="hf-{{ $i }}" class="flex flex-wrap items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <input type="text" wire:model="headerFields.{{ $i }}.label" class="input" placeholder="Label" />
                                    @error("headerFields.{$i}.label") <p class="error-text">{{ $message }}</p> @enderror
                                </div>
                                <select wire:model="headerFields.{{ $i }}.type" class="input w-44">
                                    @foreach ($headerTypes as $type)
                                        <option value="{{ $type }}">{{ \App\Models\AuditTemplate::HEADER_TYPE_LABELS[$type] ?? ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                                <label class="inline-flex h-11 items-center gap-1.5 text-xs text-gray-600">
                                    <input type="checkbox" wire:model="headerFields.{{ $i }}.required" class="rounded border-gray-300 text-brand-600" /> Required
                                </label>
                                <button type="button" wire:click="removeHeaderField({{ $i }})" class="icon-btn icon-btn-danger" title="Remove">
                                    <x-icon name="close" size="h-4 w-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary">Save details</button>
                </div>
            </form>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-[16rem_1fr]">
        {{-- Sections --}}
        <div class="card p-3">
            <div class="mb-2 flex items-center justify-between px-2">
                <span class="label">Sections</span>
                <button type="button" wire:click="addSection" class="btn-ghost text-xs">+ Section</button>
            </div>

            <ul class="space-y-1">
                @forelse ($sections as $s)
                    <li wire:key="sec-{{ $s->id }}">
                        <button type="button" wire:click="selectSection({{ $s->id }})"
                                class="list-row rounded-control {{ $sectionId === $s->id ? 'bg-brand-50 text-brand-800' : 'hover:bg-gray-50 text-gray-700' }}">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $s->name }}</span>
                                <span class="block text-xs text-gray-600">
                                    {{ $sectionPoints[$s->id] ?? 0 }} pts{{ $s->isPenalty() ? ' · penalty' : '' }}
                                </span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li class="px-2 py-4 text-sm text-gray-600">No sections yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- Current section --}}
        <div class="min-w-0">
            @if ($sectionId && isset($items) )
                <div class="card mb-4 p-5">
                    <div class="grid gap-3 sm:grid-cols-[1fr_1fr_10rem]">
                        <div>
                            <label class="label" for="sec-name">Section name</label>
                            <input id="sec-name" type="text" wire:model.blur="sectionName" wire:change="saveSection" class="input" />
                            @error('sectionName') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="sec-alt">{{ $altLanguage ?: 'Second language' }}</label>
                            <input id="sec-alt" type="text" wire:model.blur="sectionNameAlt" wire:change="saveSection" class="input" />
                        </div>
                        <div>
                            <label class="label" for="sec-mode">Scoring</label>
                            <select id="sec-mode" wire:model="sectionMode" wire:change="saveSection" class="input">
                                <option value="area">Area (pools)</option>
                                <option value="penalty">Penalty</option>
                            </select>
                        </div>
                    </div>
                    <p class="help mt-2">
                        An <strong>area</strong> section's points pool into the audit total. A <strong>penalty</strong> section's lost points are
                        subtracted from that total afterwards and its points are never in the pool — for critical items that fail the whole audit.
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="moveSection({{ $sectionId }}, -1)" class="btn-ghost text-xs">Move up</button>
                        <button type="button" wire:click="moveSection({{ $sectionId }}, 1)" class="btn-ghost text-xs">Move down</button>
                        @canDo('audits.delete')
                            <button type="button" wire:click="deleteSection({{ $sectionId }})" class="btn-ghost text-xs text-danger-700"
                                    data-confirm-delete="Delete the section “{{ $sectionName }}” and every item in it from this form. Audits already conducted keep their own copy.">
                                Delete section
                            </button>
                        @endcanDo
                    </div>
                </div>

                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                        <x-card-title>Items</x-card-title>
                        <button type="button" wire:click="addItem()" class="btn-secondary">+ Item</button>
                    </div>

                    @if (empty($items))
                        <div class="empty-state py-10">
                            <p class="empty-title">No items in this section</p>
                            <p class="empty-body">Add an item. Give it sub-items when one heading covers several things — equipment, utensils.</p>
                        </div>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($items as $id => $item)
                                <li wire:key="item-{{ $id }}" class="px-3 py-2 {{ $item['depth'] ? 'bg-gray-50/60 pl-8 sm:pl-12' : '' }}">
                                    <div class="flex flex-wrap items-start gap-2">
                                        <input type="text" wire:model.blur="items.{{ $id }}.number" class="input w-14 text-center" placeholder="#" aria-label="Number" />

                                        <div class="min-w-[12rem] flex-1 space-y-1">
                                            <input type="text" wire:model.blur="items.{{ $id }}.label" class="input {{ $item['depth'] ? '' : 'font-medium' }}" placeholder="Item" aria-label="Label" />
                                            @if ($altLanguage)
                                                <input type="text" wire:model.blur="items.{{ $id }}.label_alt" class="input text-gray-600" placeholder="{{ $altLanguage }}" aria-label="{{ $altLanguage }} label" />
                                            @endif
                                            @if (! $item['has_children'])
                                                <input type="text" wire:model.blur="items.{{ $id }}.hint" class="input text-xs" placeholder="Hint — e.g. Setting: 0°C – 4°C" aria-label="Hint" />
                                            @endif
                                        </div>

                                        @unless ($item['has_children'])
                                            <select wire:model="items.{{ $id }}.type" class="input w-28" aria-label="Type">
                                                <option value="check">Check</option>
                                                <option value="product">Product</option>
                                                <option value="info">Info</option>
                                            </select>
                                            @if ($item['type'] === 'info')
                                                <select wire:model="items.{{ $id }}.info_type" class="input w-28" aria-label="Info type">
                                                    @foreach ($infoTypes as $it)
                                                        <option value="{{ $it }}">{{ ucfirst($it) }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <div class="w-20">
                                                    <input type="number" min="0" max="999" wire:model.blur="items.{{ $id }}.points" class="input text-center" aria-label="Points" />
                                                </div>
                                            @endif
                                        @else
                                            <span class="badge-neutral self-center">heading</span>
                                        @endunless

                                        <div class="flex items-center gap-0.5">
                                            <button type="button" wire:click="moveItem({{ $id }}, -1)" class="icon-btn" title="Move up"><x-icon name="chevron-down" size="h-4 w-4" class="rotate-180" /></button>
                                            <button type="button" wire:click="moveItem({{ $id }}, 1)" class="icon-btn" title="Move down"><x-icon name="chevron-down" size="h-4 w-4" /></button>
                                            @if (! $item['depth'] && $item['type'] !== 'info')
                                                <button type="button" wire:click="addItem({{ $id }})" class="icon-btn" title="Add sub-item">
                                                    <span class="text-base font-semibold leading-none">+</span>
                                                </button>
                                            @endif
                                            <button type="button" wire:click="deleteItem({{ $id }})" class="icon-btn icon-btn-danger" title="Delete"
                                                    data-confirm-delete="Delete “{{ $item['label'] }}”{{ $item['has_children'] ? ' and its sub-items' : '' }} from this form.">
                                                <x-icon name="trash" size="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                    @if ($item['type'] === 'product' && ! $item['depth'])
                                        <p class="help mt-1">Product slot: the auditor names the product on the day and scores the sub-items under it.</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @else
                <div class="card p-10">
                    <div class="empty-state">
                        <p class="empty-title">Add a section to start</p>
                        <p class="empty-body">Sections are the score cards on the summary — Bar, Kitchen, Service.</p>
                        <button type="button" wire:click="addSection" class="btn-primary">+ Section</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
