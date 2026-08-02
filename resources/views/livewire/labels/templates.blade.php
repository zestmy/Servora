<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="page-eyebrow">Labels / Templates</p>
            <h1 class="page-title mt-1">Label templates</h1>
        </div>
        <button wire:click="openCreate" class="btn-primary">
            + New template
        </button>
    </div>

    <div class="card p-5 mb-4">
        <p class="text-sm text-gray-600">
            One default template per label type decides what prints. Everything else is a spare.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            To customise, <strong>duplicate</strong> a template and edit the copy, then make it the default.
            That keeps the original as a fallback — a company that mangles its only use-by template can't print
            the most-used label in the system.
        </p>
    </div>

    @foreach ($labelTypes as $type => $caption)
        <div class="mb-4">
            <h3 class="text-xs uppercase tracking-wider text-gray-600 mb-2">
                {{ $caption ?: 'Custom' }} <span class="text-gray-500">· {{ $type }}</span>
            </h3>

            <div class="card overflow-hidden">
                <div class="divide-y divide-gray-50">
                    @forelse ($templates[$type] ?? [] as $template)
                        <div class="px-4 py-3 flex flex-wrap items-center gap-3" wire:key="tpl-{{ $template->id }}">
                            <div class="flex-1 min-w-[160px]">
                                <p class="text-sm font-medium text-gray-700">
                                    {{ $template->name }}
                                    @if ($template->is_default)
                                        <span class="ml-1 px-1.5 py-0.5 rounded bg-success-50 text-success-700 text-[10px] align-middle">Default</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-600 mt-0.5">
                                    {{ rtrim(rtrim($template->width_mm, '0'), '.') }}×{{ rtrim(rtrim($template->height_mm, '0'), '.') }}mm
                                    · {{ count($template->fields()) }} fields
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('labels.templates.design', $template) }}" wire:navigate
                                   class="px-3 py-1.5 text-xs text-white bg-brand-600 rounded-lg hover:bg-brand-700">Design</a>
                                <button wire:click="duplicate({{ $template->id }})"
                                        class="btn-secondary btn-sm">Duplicate</button>
                                @unless ($template->is_default)
                                    <button wire:click="makeDefault({{ $template->id }})"
                                            class="text-xs text-brand-600 hover:underline">Make default</button>
                                    <button wire:click="delete({{ $template->id }})" wire:confirm="Delete this template?"
                                            class="text-xs text-danger-500 hover:underline">Delete</button>
                                @endunless
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-gray-600 text-sm">No template for this type.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach

    <div x-data="{ open: @entangle('showModal') }">
        <template x-teleport="body">
            <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-[100] overflow-y-auto">
                <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
                <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
                        <div class="px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">New template</h3>
                        </div>
                        <form wire:submit.prevent="create" class="p-5 space-y-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Name <span class="text-danger-500">*</span></label>
                                <input type="text" wire:model="name" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Label type</label>
                                <select wire:model="label_type" class="mt-1 w-full text-sm rounded-lg border-gray-300">
                                    @foreach ($labelTypes as $type => $caption)
                                        <option value="{{ $type }}">{{ $caption ?: 'Custom' }} ({{ $type }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs font-semibold text-gray-600">Width (mm)</label>
                                    <input type="number" step="0.5" wire:model="width_mm" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                    <x-input-error :messages="$errors->get('width_mm')" class="mt-1" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-600">Height (mm)</label>
                                    <input type="number" step="0.5" wire:model="height_mm" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                                    <x-input-error :messages="$errors->get('height_mm')" class="mt-1" />
                                </div>
                            </div>
                            <p class="text-xs text-gray-600">Starts from the standard arrangement, which you can then rearrange.</p>
                            <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                                <button type="button" @click="open = false" class="btn-secondary">Cancel</button>
                                <button type="submit" class="px-4 py-2 text-sm text-white bg-brand-600 rounded-lg hover:bg-brand-700">Create &amp; design</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
