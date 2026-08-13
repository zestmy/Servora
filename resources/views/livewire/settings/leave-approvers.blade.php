<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <a data-back href="{{ route('settings.index', ['module' => 'hr-people']) }}" class="text-gray-600 hover:text-gray-900 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <p class="page-eyebrow">Settings / HR</p>
                <h1 class="page-title mt-1">Leave Approvers</h1>
            </div>
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
            Leave normally goes to the employee's own <strong>superior</strong>, set on their record under
            HR → Employees → Employment. This screen is the <strong>fallback</strong> for anyone without a
            reporting line, and the answer for companies that approve centrally rather than up a chain.
        </p>
        <p class="text-xs text-gray-600 mt-1">
            Both are notified when both apply, and nobody is emailed twice. An approver with no outlet covers
            the whole company; one with an outlet and no section covers that whole outlet.
        </p>
    </div>

    <div class="card p-5 mb-4">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Add an approver</h3>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="label">User <span class="text-danger-500">*</span></label>
                <select wire:model="user_id" class="input">
                    <option value="">— Select —</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('user_id')" class="mt-1" />
            </div>
            <div>
                <label class="label">Outlet</label>
                <select wire:model="outlet_id" class="input">
                    <option value="">Every outlet</option>
                    @foreach ($outlets as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Section</label>
                <select wire:model="section_id" class="input">
                    <option value="">Every section</option>
                    @foreach ($sections as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end pb-1">
                <button wire:click="add" class="btn-primary">Add</button>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[640px]">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Approver</th>
                        <th class="px-2 py-2 text-left">Outlet</th>
                        <th class="px-2 py-2 text-left">Section</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($approvers as $a)
                        <tr wire:key="la-{{ $a->id }}" class="hover:bg-gray-50">
                            <td class="px-3 py-2">
                                <span class="font-medium text-gray-800">{{ $a->user?->name ?? '—' }}</span>
                                {{-- The address the notification will go to, shown
                                     here so a missing one is visible before a
                                     request silently reaches nobody. --}}
                                <span class="block text-[11px] {{ $a->user?->email ? 'text-gray-500' : 'text-danger-600' }}">
                                    {{ $a->user?->email ?: 'no email — will not be notified' }}
                                </span>
                            </td>
                            <td class="px-2 py-2 text-sm text-gray-700">{{ $a->outlet?->name ?? 'Every outlet' }}</td>
                            <td class="px-2 py-2 text-sm text-gray-700">{{ $a->section?->name ?? 'Every section' }}</td>
                            <td class="px-2 py-2 text-right">
                                <button wire:click="remove({{ $a->id }})" data-confirm-delete="Remove this approver?"
                                        class="text-xs font-medium text-danger-600 hover:text-danger-800">Remove</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-gray-600">
                            No leave approvers yet. Requests will route to each employee's superior only.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Named rather than left to be discovered: an employee with no superior
         and no matching approver applies for leave that reaches nobody. --}}
    @if ($unrouted->isNotEmpty() && $approvers->isEmpty())
        <div class="alert-warning">
            <p class="font-medium text-sm">{{ $unrouted->count() }} employee(s) have no superior set</p>
            <p class="text-sm mt-0.5">
                With no approver here either, their leave requests will be saved but nobody will be
                notified. Set a superior on each employee's record, or add a company-wide approver above.
            </p>
            <p class="text-xs mt-1">
                {{ $unrouted->take(12)->pluck('name')->join(', ') }}@if ($unrouted->count() > 12) and {{ $unrouted->count() - 12 }} more @endif.
            </p>
        </div>
    @elseif ($unrouted->isNotEmpty())
        <p class="text-xs text-gray-600">
            {{ $unrouted->count() }} employee(s) have no superior set — their leave routes to the approvers above.
        </p>
    @endif
</div>
