<div>
    {{-- Flash --}}
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / Approvers</p>
        </div>
    </div>

    {{-- ── Settings Toggles ─────────────────────────────────────────────── --}}

    <div class="space-y-4 mb-8">
        {{-- Ordering Mode --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Ordering Mode</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $orderingMode === 'cpu' ? 'CPU mode — outlets submit PRs, CPU consolidates and creates POs.' : 'Direct mode — outlets create POs directly to suppliers.' }}
                    </p>
                </div>
                <select wire:model.live="orderingMode" class="rounded-lg border-gray-300 text-sm">
                    <option value="direct">Direct to Supplier</option>
                    <option value="cpu">Via Central Purchasing (CPU)</option>
                </select>
            </div>
        </div>

        {{-- PR Approval --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Require PR Approval</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $requirePrApproval ? 'Submitted PRs must be approved before processing.' : 'PRs are auto-approved on submission.' }}
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="requirePrApproval" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>
        </div>

        {{-- PO Approval --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Require PO Approval</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $requirePoApproval ? 'Submitted POs must be approved by an appointed approver.' : 'POs are auto-approved on submission.' }}
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="requirePoApproval" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>
        </div>
    </div>

    {{-- ── PO Approvers ─────────────────────────────────────────────────── --}}

    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-700">PO Approvers</h2>
            <button wire:click="openAdd('po')" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                + Add PO Approver
            </button>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if ($poApprovers->count() > 0)
                <div class="overflow-x-auto"><table class="min-w-[900px] divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3 text-left">User</th>
                            <th class="px-5 py-3 text-left">Outlets</th>
                            <th class="px-5 py-3 text-left">Departments</th>
                            <th class="px-5 py-3 text-center w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($poApprovers as $userId => $data)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-800">{{ $data['user']?->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $data['user']?->email }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($data['is_all_outlets'])
                                        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-xs rounded-full font-medium">All Outlets</span>
                                    @else
                                        <span class="text-xs text-gray-600">{{ $data['outlets'] }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($data['is_all_depts'])
                                        <span class="px-2 py-0.5 bg-green-50 text-green-600 text-xs rounded-full font-medium">All Departments</span>
                                    @else
                                        <span class="text-xs text-gray-600">{{ $data['departments'] }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <button wire:click="openEdit('po', {{ $userId }})" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium mr-2">Edit</button>
                                    <button wire:click="removeApprover('po', {{ $userId }})" wire:confirm="Remove this PO approver?" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @else
                <div class="p-8 text-center text-gray-400 text-sm">No PO approvers assigned. Click "+ Add PO Approver" to get started.</div>
            @endif
        </div>
    </div>

    {{-- ── PR Approvers ─────────────────────────────────────────────────── --}}

    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-700">PR Approvers</h2>
            <button wire:click="openAdd('pr')" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                + Add PR Approver
            </button>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if ($prApprovers->count() > 0)
                <div class="overflow-x-auto"><table class="min-w-[900px] divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3 text-left">User</th>
                            <th class="px-5 py-3 text-left">Outlets</th>
                            <th class="px-5 py-3 text-left">Departments</th>
                            <th class="px-5 py-3 text-center w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($prApprovers as $userId => $data)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-800">{{ $data['user']?->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $data['user']?->email }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($data['is_all_outlets'])
                                        <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-xs rounded-full font-medium">All Outlets</span>
                                    @else
                                        <span class="text-xs text-gray-600">{{ $data['outlets'] }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($data['is_all_depts'])
                                        <span class="px-2 py-0.5 bg-green-50 text-green-600 text-xs rounded-full font-medium">All Departments</span>
                                    @else
                                        <span class="text-xs text-gray-600">{{ $data['departments'] }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <button wire:click="openEdit('pr', {{ $userId }})" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium mr-2">Edit</button>
                                    <button wire:click="removeApprover('pr', {{ $userId }})" wire:confirm="Remove this PR approver?" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @else
                <div class="p-8 text-center text-gray-400 text-sm">No PR approvers assigned. Click "+ Add PR Approver" to get started.</div>
            @endif
        </div>
    </div>

    {{-- ── Unified Add/Edit Modal ────────────────────────────────────────── --}}

    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-900/50" wire:click="$set('showModal', false)"></div>
                <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">
                        {{ $editUserId ? 'Edit' : 'Add' }} {{ $modalType === 'po' ? 'PO' : 'PR' }} Approver
                    </h3>

                    <div class="space-y-5">
                        {{-- User --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">User *</label>
                            <select wire:model="selectedUserId" {{ $editUserId ? 'disabled' : '' }}
                                    class="w-full rounded-lg border-gray-300 text-sm {{ $editUserId ? 'bg-gray-50' : '' }}">
                                <option value="">— Select user —</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            @error('selectedUserId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Outlets --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-2">Outlets *</label>
                            <label class="flex items-center gap-2 mb-2 px-2 py-1.5 bg-indigo-50 rounded-lg cursor-pointer">
                                <input type="checkbox" wire:model.live="allOutlets" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm font-medium text-indigo-700">All Outlets</span>
                            </label>
                            @if (! $allOutlets)
                                <div class="max-h-32 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
                                    @foreach ($outlets as $o)
                                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                                            <input type="checkbox" wire:model="selectedOutletIds" value="{{ $o->id }}"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                            <span class="text-sm text-gray-700">{{ $o->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            @error('selectedOutletIds') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Departments --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-2">Departments *</label>
                            <label class="flex items-center gap-2 mb-2 px-2 py-1.5 bg-green-50 rounded-lg cursor-pointer">
                                <input type="checkbox" wire:model.live="allDepartments" class="rounded border-gray-300 text-green-600 focus:ring-green-500" />
                                <span class="text-sm font-medium text-green-700">All Departments</span>
                            </label>
                            @if (! $allDepartments)
                                <div class="max-h-32 overflow-y-auto border border-gray-200 rounded-lg p-2 space-y-1">
                                    @foreach ($departments as $dept)
                                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                                            <input type="checkbox" wire:model="selectedDepartmentIds" value="{{ $dept->id }}"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                            <span class="text-sm text-gray-700">{{ $dept->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            @error('selectedDepartmentIds') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button wire:click="$set('showModal', false)" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                        <button wire:click="save" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                            {{ $editUserId ? 'Update' : 'Assign' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
