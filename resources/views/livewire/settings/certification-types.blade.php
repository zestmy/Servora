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
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('settings.index') }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="page-eyebrow">Settings / Certifications &amp; Training</p>
                <h1 class="page-title mt-1">Certifications &amp; Training</h1>
            </div>
        </div>
        <button wire:click="openCreate" class="btn-primary">
            <span class="sm:hidden">+ Add</span>
            <span class="hidden sm:inline">+ Add Certification</span>
        </button>
    </div>

    <div class="card p-5 mb-4">
        <p class="text-sm text-gray-600">
            Courses and certificates you track against staff — barista training, chemical handling, fire safety,
            and anything else your outlets run. Once added here, a course can be recorded on an employee with its
            certificate number and dates.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            Typhoid Card, Food Handler and Halal Awareness Training are built in and appear on every employee
            already — you don't need to add them here.
        </p>
    </div>

    {{-- Built-in documents: which of them actually lapse --}}
    <div class="card p-5 mb-4">
        <h3 class="text-sm font-semibold text-gray-700">Built-in documents</h3>
        <p class="text-xs text-gray-500 mt-1">
            Only documents that expire appear in the Employees compliance card and the reminder email —
            an expiry report has nothing to say about a certificate that is attended once and never renewed.
            Whether staff hold them is still shown on the Employees list either way.
        </p>
        <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model="typhoid_expires" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700">Typhoid Card expires</span>
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model="food_handler_expires" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700">Food Handler expires</span>
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model="halal_training_expires" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700">Halal Awareness Training expires</span>
            </label>
        </div>
        <div class="flex justify-end mt-4">
            <button wire:click="saveBuiltIns" class="btn-primary">Save</button>
        </div>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-surface min-w-[820px]">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-center w-28">Expires</th>
                    <th class="px-4 py-3 text-center w-32">Required</th>
                    <th class="px-4 py-3 text-center w-24">Sort Order</th>
                    <th class="px-4 py-3 text-center w-28">Employees</th>
                    <th class="px-4 py-3 text-center w-28">Status</th>
                    <th class="px-4 py-3 text-center w-32">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($types as $type)
                    <tr wire:key="cert-type-{{ $type->id }}" class="hover:bg-gray-50 {{ ! $type->is_active ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800">{{ $type->name }}</p>
                            @if ($type->description)
                                <p class="text-xs text-gray-600 mt-0.5">{{ $type->description }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $type->has_expiry ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $type->has_expiry ? 'Yes' : 'One-off' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $type->is_required ? 'bg-warning-100 text-warning-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $type->is_required ? 'All staff' : 'Optional' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $type->sort_order }}</td>
                        <td class="px-4 py-3 text-center text-gray-500">{{ $usage[$type->id] ?? 0 }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $type->is_active ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $type->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-1">
                                <button wire:click="openEdit({{ $type->id }})" title="Edit" class="text-brand-500 hover:text-brand-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button wire:click="toggleActive({{ $type->id }})" title="{{ $type->is_active ? 'Deactivate' : 'Activate' }}" class="text-warning-500 hover:text-warning-700 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button wire:click="delete({{ $type->id }})" wire:confirm="Delete this certification?" title="Delete" class="text-danger-400 hover:text-danger-600 p-1">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-600">No certifications yet. Add one to start recording it against staff.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    {{-- Modal (teleported to body) --}}
    <div x-data="{ open: @entangle('showModal') }">
    <template x-teleport="body">
        <div x-show="open" x-cloak
             @keydown.escape.window="open = false"
             class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="fixed inset-0 bg-black/50" @click="open = false"></div>
            <div class="relative min-h-full flex items-start sm:items-center justify-center p-4">
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $editingId ? 'Edit Certification' : 'Add Certification' }}</h3>
                        <button @click="open = false" class="text-gray-600 hover:text-gray-900 p-1">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form wire:submit.prevent="save" class="p-5 space-y-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Name <span class="text-danger-500">*</span></label>
                            <input type="text" wire:model="name" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="e.g. Fire Safety, Barista Level 2" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Description</label>
                            <textarea wire:model="description" rows="2" class="mt-1 w-full text-sm rounded-lg border-gray-300" placeholder="What it covers, who runs it…"></textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Sort Order</label>
                            <input type="number" wire:model="sort_order" class="mt-1 w-full text-sm rounded-lg border-gray-300" />
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 space-y-2">
                            <label class="flex items-start gap-2">
                                <input type="checkbox" wire:model="has_expiry" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="text-sm text-gray-700">
                                    This certificate expires
                                    <span class="block text-[11px] text-gray-500">Untick for a one-off course that never lapses — it will not be chased for renewal.</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input type="checkbox" wire:model="is_required" class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="text-sm text-gray-700">
                                    Every employee should hold this
                                    <span class="block text-[11px] text-gray-500">Staff without it are listed as missing. Leave unticked for specialist courses, or the whole team is reported.</span>
                                </span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                            <button type="button" @click="open = false" class="btn-secondary">Cancel</button>
                            <button type="submit" class="btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </template>
    </div>
</div>
