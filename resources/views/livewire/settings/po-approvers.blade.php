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
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="flex-1">
            <p class="text-xs text-gray-400"><a href="{{ route('settings.index') }}" class="hover:underline">Settings</a> / PO Approvers</p>
        </div>
    </div>

    {{-- Approval Toggle --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-800">Require PO Approval</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    @if ($requirePoApproval)
                        Submitted POs must be approved by an appointed approver before the Purchasing team can process them.
                    @else
                        POs will be auto-approved on submission and sent directly to the Purchasing team.
                    @endif
                </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" wire:model.live="requirePoApproval" class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
            </label>
        </div>
    </div>

    {{-- Guide --}}
    @if ($requirePoApproval)
    <div x-data="{ open: false }" class="mb-4">
        <button @click="open = !open" class="flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform" :class="open && 'rotate-90'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            How does PO approval work?
        </button>
        <div x-show="open" x-collapse x-cloak class="mt-3 bg-indigo-50 border border-indigo-100 rounded-xl p-5 text-sm text-gray-700 space-y-3">
            <p><strong>PO Approval Flow:</strong> When an outlet submits a Purchase Order, an appointed approver must review and approve it before the Purchasing team can convert it into a Delivery Order.</p>
            <div class="space-y-1.5">
                <p class="font-semibold text-gray-800">How to set up:</p>
                <ol class="list-decimal list-inside space-y-1 ml-1">
                    <li>For each outlet/branch, assign one or more users who can approve POs.</li>
                    <li>Eligible roles: <strong>Operations Manager</strong>, <strong>Outlet Manager</strong>, or <strong>Chef</strong>.</li>
                    <li>Appointed approvers will see a PO Approval Queue on their dashboard and in the Purchasing module.</li>
                    <li>The approver's name will be recorded on the PO document as the "Approved By" person.</li>
                </ol>
            </div>
            <p class="text-xs text-gray-500">A user can be assigned as approver for multiple outlets. Each outlet can have multiple approvers.</p>
        </div>
    </div>
    @endif

    {{-- Outlets & Approvers --}}
    @if ($requirePoApproval)
    <div class="space-y-4">
        @foreach ($outlets as $outlet)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 text-sm">{{ $outlet->name }}</p>
                            @if ($outlet->address)
                                <p class="text-xs text-gray-400">{{ $outlet->address }}</p>
                            @endif
                        </div>
                    </div>
                    <button wire:click="openAssign({{ $outlet->id }})"
                            class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700 transition">
                        + Assign Approver
                    </button>
                </div>

                @php $outletApprovers = $approvers->get($outlet->id, collect()); @endphp

                @if ($outletApprovers->count() > 0)
                    <div class="divide-y divide-gray-50">
                        @foreach ($outletApprovers as $pa)
                            <div class="px-5 py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-xs font-bold text-gray-600 uppercase">
                                        {{ substr($pa->user?->name ?? '?', 0, 2) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">{{ $pa->user?->name ?? '—' }}</p>
                                        <p class="text-xs text-gray-400">{{ $pa->user?->roles->first()?->name ?? '' }} &middot; {{ $pa->user?->email }}</p>
                                    </div>
                                </div>
                                <button wire:click="remove({{ $pa->id }})"
                                        wire:confirm="Remove {{ $pa->user?->name }} as approver for {{ $outlet->name }}?"
                                        class="text-red-400 hover:text-red-600 transition p-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-5 py-6 text-center text-gray-400 text-sm">
                        No approvers assigned. POs from this outlet cannot be approved until an approver is assigned.
                    </div>
                @endif
            </div>
        @endforeach

        @if ($outlets->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
                <p class="font-medium">No outlets configured</p>
                <p class="text-xs mt-1">Create outlets in <a href="{{ route('settings.outlets') }}" class="text-indigo-600 underline">Branches</a> first.</p>
            </div>
        @endif
    </div>
    @endif

    {{-- Assign Modal --}}
    <div x-data="{}" x-show="$wire.showModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 z-10">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-800">
                    Assign PO Approver — {{ $editingOutlet?->name ?? '' }}
                </h3>
                <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit="assign">
                <div class="px-6 py-5 space-y-4">
                    <div>
                        <x-input-label value="Select User *" />
                        <select wire:model="selectedUserId"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">— Choose a user —</option>
                            @foreach ($eligibleUsers as $eu)
                                <option value="{{ $eu->id }}">{{ $eu->name }} ({{ $eu->roles->first()?->name }})</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Eligible roles: Operations Manager, Outlet Manager, Chef</p>
                        <x-input-error :messages="$errors->get('selectedUserId')" class="mt-1" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
                    <button type="button" @click="$wire.closeModal()"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Assign Approver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
