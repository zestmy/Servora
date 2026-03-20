<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / LMS Users</p>
            <h2 class="text-lg font-semibold text-gray-700 mt-1">LMS User Management</h2>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <div class="flex rounded-lg overflow-hidden border border-gray-200 bg-white text-sm">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label)
                <button wire:click="$set('statusFilter', '{{ $val }}')"
                        class="px-4 py-2 font-medium transition {{ $statusFilter === $val ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="flex-1">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email..."
                   class="w-full sm:max-w-xs rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        @if ($users->count())
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Phone</th>
                            <th class="px-4 py-3 text-left">Outlet</th>
                            <th class="px-4 py-3 text-left">Registered</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->phone ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->outlet?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $user->created_at->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->status === 'pending')
                                        <span class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">Pending</span>
                                    @elseif ($user->status === 'approved')
                                        <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">Approved</span>
                                        @if ($user->approver)
                                            <p class="text-xs text-gray-400 mt-0.5">by {{ $user->approver->name }}</p>
                                        @endif
                                    @else
                                        <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-semibold rounded-full">Rejected</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($user->status === 'pending')
                                        <div class="flex items-center justify-center gap-2">
                                            <button wire:click="approve({{ $user->id }})"
                                                    wire:confirm="Approve {{ $user->name }}?"
                                                    class="px-3 py-1 text-xs font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                                                Approve
                                            </button>
                                            <button wire:click="reject({{ $user->id }})"
                                                    wire:confirm="Reject {{ $user->name }}?"
                                                    class="px-3 py-1 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">
                                                Reject
                                            </button>
                                        </div>
                                    @elseif ($user->status === 'rejected')
                                        <button wire:click="approve({{ $user->id }})"
                                                class="px-3 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                                            Approve
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-100">
                {{ $users->links() }}
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-400">
                <p class="font-medium">No {{ $statusFilter }} LMS users found.</p>
            </div>
        @endif
    </div>
</div>
