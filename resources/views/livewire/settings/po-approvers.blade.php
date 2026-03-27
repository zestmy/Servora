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

    {{-- Ordering Mode Toggle --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-800">Ordering Mode</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    @if ($orderingMode === 'cpu')
                        CPU mode — outlets submit Purchase Requests, CPU consolidates and creates POs to suppliers.
                    @else
                        Direct mode — outlets create Purchase Orders directly to suppliers.
                    @endif
                </p>
            </div>
            <select wire:model.live="orderingMode" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="direct">Direct to Supplier</option>
                <option value="cpu">Via Central Purchasing (CPU)</option>
            </select>
        </div>
    </div>

    {{-- PR Approval Toggle (CPU mode only) --}}
    @if ($orderingMode === 'cpu')
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Require PR Approval</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if ($requirePrApproval)
                            Submitted Purchase Requests must be approved before CPU can consolidate them.
                        @else
                            Purchase Requests are auto-approved on submission.
                        @endif
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model.live="requirePrApproval" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>
        </div>
    @endif

    {{-- PO Approval Toggle --}}
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
            <p><strong>PO Approval Flow:</strong> When an outlet submits a Purchase Order, an appointed approver must review and approve it before the Purchasing team can process them.</p>
            <div class="space-y-1.5">
                <p class="font-semibold text-gray-800">How to set up:</p>
                <ol class="list-decimal list-inside space-y-1 ml-1">
                    <li>For each outlet, assign one or more users as PO approvers.</li>
                    <li>Select which departments each approver can approve for.</li>
                    <li>Eligible roles: <strong>Operations Manager</strong>, <strong>Branch Manager</strong>, or <strong>Chef</strong>.</li>
                    <li>Approvers will see a PO Approval Queue on their dashboard for their assigned departments.</li>
                </ol>
            </div>
            <p class="text-xs text-gray-500">A user can be assigned to different departments across multiple outlets.</p>
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

                @php $outletUsers = $approversByOutlet->get($outlet->id, collect()); @endphp

                @if ($outletUsers->count() > 0)
                    <div class="divide-y divide-gray-50">
                        @foreach ($outletUsers as $userId => $userRecords)
                            @php $user = $userRecords->first()->user; @endphp
                            <div class="px-5 py-3">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-xs font-bold text-gray-600 uppercase mt-0.5">
                                            {{ substr($user?->name ?? '?', 0, 2) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">{{ $user?->name ?? '—' }}</p>
                                            <p class="text-xs text-gray-400">{{ $user?->roles->first()?->name ?? '' }} &middot; {{ $user?->email }}</p>
                                            {{-- Department tags --}}
                                            <div class="flex flex-wrap gap-1.5 mt-2">
                                                @foreach ($userRecords as $pa)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                        {{ $pa->department?->name ?? 'Unknown' }}
                                                        <button wire:click="removeDept({{ $pa->id }})"
                                                                wire:confirm="Remove {{ $pa->department?->name }} from {{ $user?->name }}?"
                                                                class="text-indigo-400 hover:text-red-500 transition ml-0.5">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <button wire:click="removeUser({{ $outlet->id }}, {{ $userId }})"
                                            wire:confirm="Remove {{ $user?->name }} from all departments in {{ $outlet->name }}?"
                                            class="text-red-400 hover:text-red-600 transition p-1 mt-0.5" title="Remove all">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
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
    @teleport('body')
    <div x-data="{}" x-show="$wire.showModal" x-cloak
         class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-gray-900/50" @click="$wire.closeModal()"></div>
        <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md z-10">
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
                        <p class="mt-1 text-xs text-gray-400">Eligible roles: Operations Manager, Branch Manager, Chef</p>
                        <x-input-error :messages="$errors->get('selectedUserId')" class="mt-1" />
                    </div>

                    {{-- Department multi-select tags --}}
                    <div x-data="{
                        open: false,
                        search: '',
                        departments: @js($departments->map(fn($d) => ['id' => $d->id, 'name' => $d->name])),
                        get selected() { return this.$wire.selectedDepartmentIds },
                        toggle(id) {
                            let ids = [...this.$wire.selectedDepartmentIds];
                            let idx = ids.indexOf(id);
                            if (idx > -1) { ids.splice(idx, 1); } else { ids.push(id); }
                            this.$wire.selectedDepartmentIds = ids;
                        },
                        isSelected(id) {
                            return this.$wire.selectedDepartmentIds.includes(id);
                        },
                        remove(id) {
                            let ids = this.$wire.selectedDepartmentIds.filter(i => i !== id);
                            this.$wire.selectedDepartmentIds = ids;
                        },
                        get filtered() {
                            let s = this.search.toLowerCase();
                            return this.departments.filter(d => d.name.toLowerCase().includes(s));
                        },
                        getName(id) {
                            let d = this.departments.find(d => d.id === id);
                            return d ? d.name : '';
                        }
                    }">
                        <x-input-label value="Departments *" />

                        {{-- Selected tags --}}
                        <div class="mt-1 min-h-[38px] flex flex-wrap items-center gap-1.5 p-2 border border-gray-300 rounded-md bg-white cursor-pointer"
                             @click="open = !open">
                            <template x-for="id in selected" :key="id">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    <span x-text="getName(id)"></span>
                                    <button type="button" @click.stop="remove(id)" class="text-indigo-400 hover:text-red-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </span>
                            </template>
                            <span x-show="selected.length === 0" class="text-sm text-gray-400">Click to select departments...</span>
                        </div>

                        {{-- Dropdown --}}
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="mt-1 border border-gray-200 rounded-lg shadow-lg bg-white max-h-48 overflow-y-auto z-20 relative">
                            <div class="sticky top-0 bg-white p-2 border-b border-gray-100">
                                <input type="text" x-model="search" placeholder="Search departments..."
                                       class="w-full text-sm border-gray-300 rounded-md px-3 py-1.5 focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <template x-for="dept in filtered" :key="dept.id">
                                <button type="button" @click="toggle(dept.id)"
                                        class="w-full px-3 py-2 text-left text-sm hover:bg-gray-50 flex items-center justify-between transition"
                                        :class="isSelected(dept.id) && 'bg-indigo-50'">
                                    <span x-text="dept.name"></span>
                                    <svg x-show="isSelected(dept.id)" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            </template>
                            <div x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-400">No departments found</div>
                        </div>

                        <p class="mt-1 text-xs text-gray-400">Select the departments this approver can approve POs for.</p>
                        <x-input-error :messages="$errors->get('selectedDepartmentIds')" class="mt-1" />
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
    </div>
    @endteleport
</div>
