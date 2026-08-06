<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('settings.index', ['module' => 'hr-people']) }}" class="text-gray-600 hover:text-gray-900 transition">
                <x-icon name="chevron-left" class="w-5 h-5" />
            </a>
            <div>
                <p class="page-eyebrow">Settings / HR</p>
                <h1 class="page-title mt-1">Employee Particulars</h1>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="restoreDefaults" class="btn-secondary">Restore defaults</button>
            <button wire:click="create" class="btn-primary">+ Add {{ $meta['singular'] }}</button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="panel p-4 mb-4">
        <p class="text-sm text-gray-700">
            The lists the employee form offers on its <strong>Personal</strong> tab. Every one of them is this
            company's own — edit, add to, or switch off whatever does not apply.
        </p>
        <p class="text-xs text-gray-600 mt-1">
            An employee record stores the <strong>value</strong>, so renaming one here moves everybody holding it
            across with the rename. Switching a value off takes it out of the picker without touching the
            records that already have it.
        </p>
    </div>

    {{-- Which list --}}
    <div class="seg mb-4 flex-wrap">
        @foreach (\App\Models\HrOption::TYPES as $key => $type)
            <button wire:click="selectType('{{ $key }}')" wire:key="type-{{ $key }}"
                    class="seg-item {{ $this->type === $key ? 'seg-item-on' : '' }}">
                {{ $type['label'] }}
            </button>
        @endforeach
    </div>

    <p class="text-xs text-gray-600 mb-4">{{ $meta['note'] }}</p>

    {{-- Add / edit --}}
    @if ($showForm)
        <div class="card p-5 mb-4 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">
                {{ $editingId ? 'Edit' : 'New' }} {{ $meta['singular'] }}
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="label">{{ $meta['label'] }} <span class="text-danger-500">*</span></label>
                    <input type="text" maxlength="60" wire:model="f_name" class="input" />
                    <p class="help">Exactly as it should read on the employee record and any export.</p>
                    <x-input-error :messages="$errors->get('f_name')" class="mt-1" />
                </div>
                <div>
                    <label class="label">Code</label>
                    <input type="text" maxlength="20" wire:model="f_code" class="input font-mono" />
                    <p class="help">Optional. For exports that code the value rather than name it.</p>
                    <x-input-error :messages="$errors->get('f_code')" class="mt-1" />
                </div>
            </div>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model="f_is_active" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700">Offer this on the employee form</span>
            </label>

            <div class="flex justify-end gap-2">
                <button wire:click="$set('showForm', false)" class="btn-ghost">Cancel</button>
                <button wire:click="save" class="btn-primary">Save</button>
            </div>
        </div>
    @endif

    {{-- Values on staff that match nothing here --}}
    @if ($offList->isNotEmpty())
        <div class="alert-warning mb-4">
            <p class="text-sm font-medium">
                {{ $offList->count() }} value(s) on employee records are not in this list.
            </p>
            <p class="text-xs mt-1">
                Left behind by a delete, or imported. Add the value back and those records rejoin the picker.
            </p>
            <ul class="mt-2 space-y-0.5">
                @foreach ($offList as $value => $count)
                    <li class="text-xs">
                        <span class="font-medium">{{ $value }}</span>
                        <span class="text-gray-600">— {{ $count }} employee(s)</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="toolbar mb-4">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" wire:model.live="showInactive" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
            <span class="text-sm text-gray-700">Show switched off</span>
        </label>
        <span class="text-xs text-gray-600 ml-auto">{{ $options->count() }} value(s)</span>
    </div>

    {{-- List --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[560px]">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">{{ $meta['label'] }}</th>
                        <th class="px-2 py-2 text-left">Code</th>
                        <th class="px-2 py-2 text-right">Employees</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($options as $option)
                        @php($count = (int) ($usage[$option->name] ?? 0))
                        <tr wire:key="opt-{{ $option->id }}" class="hover:bg-gray-50 {{ $option->is_active ? '' : 'opacity-50' }}">
                            <td class="px-3 py-2">
                                <span class="font-medium text-gray-800">{{ $option->name }}</span>
                                @unless ($option->is_active)
                                    <span class="badge-neutral ml-2">Switched off</span>
                                @endunless
                            </td>
                            <td class="px-2 py-2 font-mono text-xs text-gray-700">{{ $option->code ?: '—' }}</td>
                            <td class="px-2 py-2 text-right tabular-nums text-gray-600">{{ $count ?: '—' }}</td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                                <button wire:click="edit({{ $option->id }})" class="text-xs font-medium text-brand-600 hover:text-brand-800">Edit</button>
                                <button wire:click="toggleActive({{ $option->id }})"
                                        class="ml-3 text-xs font-medium text-gray-600 hover:text-gray-900">
                                    {{ $option->is_active ? 'Switch off' : 'Switch on' }}
                                </button>
                                @if ($count === 0)
                                    <button wire:click="delete({{ $option->id }})" wire:confirm="Remove {{ $option->name }} from this list?"
                                            class="ml-3 text-xs font-medium text-danger-600 hover:text-danger-800">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8">
                                <div class="empty-state">
                                    <p class="text-sm text-gray-700">Nothing in this list.</p>
                                    <p class="text-xs text-gray-600 mt-1">
                                        Until there is, the employee form's {{ $meta['label'] }} picker is empty —
                                        <button wire:click="restoreDefaults" class="text-brand-600 hover:text-brand-800 font-medium">restore the defaults</button>
                                        or add your own.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
