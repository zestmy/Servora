<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('settings.index', ['module' => 'hr-people']) }}" class="text-gray-600 hover:text-gray-900 transition">
                <x-icon name="chevron-left" class="w-5 h-5" />
            </a>
            <div>
                <p class="page-eyebrow">Settings / HR</p>
                <h1 class="page-title mt-1">Banks</h1>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="restoreStandardList" class="btn-secondary">Restore standard list</button>
            <button wire:click="create" class="btn-primary">+ Add bank</button>
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
            The banks the employee form offers under <strong>Pay &amp; bank details</strong>. Seeded from the
            IBG participating list — edit it freely, it is this company's own copy.
        </p>
        <p class="text-xs text-gray-600 mt-1">
            An employee record stores the bank's <strong>name</strong>, so renaming one here moves everybody
            paid into it across with the rename. Switching a bank off takes it out of the picker without
            touching the staff who already bank there.
        </p>
    </div>

    {{-- Add / edit --}}
    @if ($showForm)
        <div class="card p-5 mb-4 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">{{ $editingId ? 'Edit' : 'New' }} bank</h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="label">Bank name <span class="text-danger-500">*</span></label>
                    <input type="text" maxlength="60" wire:model="f_name" class="input" placeholder="e.g. Maybank" />
                    <p class="help">Exactly as it should print on the payslip and the payment file.</p>
                    <x-input-error :messages="$errors->get('f_name')" class="mt-1" />
                </div>
                <div>
                    <label class="label">BIC / SWIFT</label>
                    <input type="text" maxlength="11" wire:model="f_bic" class="input font-mono uppercase" placeholder="MBBEMYKL" />
                    <p class="help">Optional. Most bulk transfer uploads ask for it.</p>
                    <x-input-error :messages="$errors->get('f_bic')" class="mt-1" />
                </div>
            </div>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model="f_is_active" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700">Offer this bank on the employee form</span>
            </label>

            <div class="flex justify-end gap-2">
                <button wire:click="$set('showForm', false)" class="btn-ghost">Cancel</button>
                <button wire:click="save" class="btn-primary">Save</button>
            </div>
        </div>
    @endif

    {{-- Bank names on staff that match nothing here --}}
    @if ($offList->isNotEmpty())
        <div class="alert-warning mb-4">
            <p class="text-sm font-medium">
                {{ $offList->count() }} bank name(s) on employee records are not in this list.
            </p>
            <p class="text-xs mt-1">
                Typed in before the picker existed, or left behind by a delete. They still print on the
                payslip, but nobody can re-pick them. Add the bank below and those records rejoin the list.
            </p>
            <ul class="mt-2 space-y-0.5">
                @foreach ($offList as $name => $count)
                    <li class="text-xs">
                        <span class="font-medium">{{ $name }}</span>
                        <span class="text-gray-600">— {{ $count }} employee(s)</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filters --}}
    <div class="toolbar mb-4">
        <input type="search" wire:model.live.debounce.300ms="search" class="input w-auto min-w-[16rem]"
               placeholder="Search bank or BIC" />
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" wire:model.live="showInactive" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
            <span class="text-sm text-gray-700">Show switched off</span>
        </label>
        <span class="text-xs text-gray-600 ml-auto">{{ $banks->count() }} of {{ $totalBanks }} shown</span>
    </div>

    {{-- List --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[640px]">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Bank</th>
                        <th class="px-2 py-2 text-left">BIC</th>
                        <th class="px-2 py-2 text-right">Employees</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($banks as $bank)
                        @php($count = (int) ($usage[$bank->name] ?? 0))
                        <tr wire:key="bank-{{ $bank->id }}" class="hover:bg-gray-50 {{ $bank->is_active ? '' : 'opacity-50' }}">
                            <td class="px-3 py-2">
                                <span class="font-medium text-gray-800">{{ $bank->name }}</span>
                                @unless ($bank->is_active)
                                    <span class="badge-neutral ml-2">Switched off</span>
                                @endunless
                            </td>
                            <td class="px-2 py-2 font-mono text-xs text-gray-700">{{ $bank->bic ?: '—' }}</td>
                            <td class="px-2 py-2 text-right tabular-nums text-gray-600">{{ $count ?: '—' }}</td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                                <button wire:click="edit({{ $bank->id }})" class="text-xs font-medium text-brand-600 hover:text-brand-800">Edit</button>
                                <button wire:click="toggleActive({{ $bank->id }})"
                                        class="ml-3 text-xs font-medium text-gray-600 hover:text-gray-900">
                                    {{ $bank->is_active ? 'Switch off' : 'Switch on' }}
                                </button>
                                @if ($count === 0)
                                    <button wire:click="delete({{ $bank->id }})" data-confirm-delete="Remove {{ $bank->name }} from the list?"
                                            class="ml-3 text-xs font-medium text-danger-600 hover:text-danger-800">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8">
                                <div class="empty-state">
                                    <p class="text-sm text-gray-700">
                                        {{ $search !== '' ? 'No bank matches that search.' : 'No banks listed.' }}
                                    </p>
                                    @if ($search === '')
                                        <p class="text-xs text-gray-600 mt-1">
                                            Until there is one, the employee form's Bank picker is empty —
                                            <button wire:click="restoreStandardList" class="text-brand-600 hover:text-brand-800 font-medium">restore the standard IBG list</button>
                                            or add them by hand.
                                        </p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
