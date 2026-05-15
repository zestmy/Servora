<div>
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
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-gray-400">HR / Duty Roster / Settings</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">Roster Approvers</h2>
            <p class="text-xs text-gray-500 mt-1">Configure users who can approve duty rosters for each outlet.</p>
        </div>
        <a href="{{ route('hr.duty-roster') }}"
           class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
            Back to Roster
        </a>
    </div>

    {{-- Outlet Selector --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700">Outlet:</label>
            <select wire:model.live="outletId" class="text-sm rounded-lg border-gray-300 shadow-sm min-w-[200px]">
                <option value="">Select an outlet...</option>
                @foreach ($outlets as $outlet)
                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($outletId)
        {{-- Add Approver --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Add Approver</label>
                    <select wire:model="selectedUserId" class="w-full text-sm rounded-lg border-gray-300 shadow-sm">
                        <option value="">Select a user...</option>
                        @foreach ($availableUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <button wire:click="addApprover"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition disabled:opacity-50"
                        {{ !$selectedUserId ? 'disabled' : '' }}>
                    Add Approver
                </button>
            </div>
        </div>

        {{-- Approvers List --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Added</th>
                        <th class="px-4 py-3 text-center w-24">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($approvers as $approver)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $approver->user->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $approver->user->email ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $approver->created_at->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="removeApprover({{ $approver->id }})"
                                        wire:confirm="Remove this approver?"
                                        class="text-red-600 hover:text-red-800 text-xs font-medium">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                No approvers configured for this outlet yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center text-gray-500">
            Please select an outlet to manage roster approvers.
        </div>
    @endif
</div>
