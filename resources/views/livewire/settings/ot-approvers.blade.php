<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div wire:key="flash-err-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / OT Approvers</p>
            <h1 class="text-lg font-bold text-gray-800">Overtime Claim Approvers</h1>
        </div>
    </div>

    {{-- Add Form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Add Approver</h3>
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <x-input-label for="approver_user" value="User *" />
                <select id="approver_user" wire:model="user_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— Select User —</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('user_id')" class="mt-1" />
            </div>
            <div class="flex-1 min-w-[180px]">
                <x-input-label for="approver_outlet" value="Outlet (blank = all)" />
                <select id="approver_outlet" wire:model="outlet_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— All Outlets —</option>
                    @foreach ($outlets as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[180px]">
                <x-input-label for="approver_section" value="Section (blank = all)" />
                <select id="approver_section" wire:model="section_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">— All Sections —</option>
                    @foreach ($sections as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-1">
                    Manage at <a href="{{ route('settings.sections') }}" class="text-indigo-600 hover:underline">Settings → Sections</a>.
                </p>
            </div>
            <button wire:click="addApprover"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                Add
            </button>
        </div>
    </div>

    {{-- Approvers List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">User</th>
                    <th class="px-4 py-3 text-left">Outlet</th>
                    <th class="px-4 py-3 text-left">Section</th>
                    <th class="px-4 py-3 text-center w-20">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($approvers as $a)
                    <tr>
                        <td class="px-4 py-3 text-gray-700 font-medium">{{ $a->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $a->outlet?->name ?? 'All Outlets' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $a->section?->name ?? 'All Sections' }}</td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="removeApprover({{ $a->id }})" wire:confirm="Remove this approver?"
                                    class="text-red-400 hover:text-red-600 text-xs font-medium">
                                Remove
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400">
                            No approvers configured yet. Add one above.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
