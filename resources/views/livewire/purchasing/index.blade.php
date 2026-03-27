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
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-700">Purchasing</h2>
        <div class="flex gap-2">
            @if ($cpuMode && $canCreatePo)
                <a href="{{ route('purchasing.requests.create') }}"
                   class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    + New Purchase Request
                </a>
            @endif
            @if ($cpuMode && $isCpuUser)
                <a href="{{ route('purchasing.consolidate') }}"
                   class="px-4 py-2 bg-white text-indigo-600 text-sm font-medium rounded-lg border border-indigo-200 hover:bg-indigo-50 transition">
                    Consolidate PRs
                </a>
                <a href="{{ route('purchasing.transfers.create') }}"
                   class="px-4 py-2 bg-white text-indigo-600 text-sm font-medium rounded-lg border border-indigo-200 hover:bg-indigo-50 transition">
                    + Stock Transfer
                </a>
            @endif
            @if (!$cpuMode && $canCreatePo)
                <a href="{{ route('purchasing.orders.create') }}"
                   class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                    + New Purchase Order
                </a>
            @endif
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($stats as $stat)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wider">{{ $stat['label'] }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-4">
        <nav class="flex gap-6 -mb-px">
            @if ($cpuMode)
                <button wire:click="$set('tab', 'pr')"
                        class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'pr' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Purchase Requests
                </button>
            @endif
            <button wire:click="$set('tab', 'po')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'po' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Purchase Orders
            </button>
            <button wire:click="$set('tab', 'do')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'do' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Delivery Orders
            </button>
            <button wire:click="$set('tab', 'grn')"
                    class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'grn' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Goods Received
            </button>
            @if ($cpuMode)
                <button wire:click="$set('tab', 'sto')"
                        class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'sto' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Stock Transfers
                </button>
            @endif
            <a href="{{ route('purchasing.rfq.index') }}"
               class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ request()->routeIs('purchasing.rfq.*') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Quotations (RFQ)
            </a>
            <a href="{{ route('purchasing.suppliers.directory') }}"
               class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ request()->routeIs('purchasing.suppliers.*') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                Find Suppliers
            </a>
        </nav>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-48">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search..."
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            @if ($seesAllOutlets)
                <select wire:model.live="outletFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @endif

            @if ($tab === 'sto')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            @elseif ($tab === 'pr')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Pending Approval</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="converted">Converted to PO</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            @elseif ($tab === 'po')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Pending Approval</option>
                    <option value="approved">Approved</option>
                    <option value="sent">Processing</option>
                    <option value="partial">Partial</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select wire:model.live="supplierFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            @elseif ($tab === 'do')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="received">Received</option>
                </select>
            @elseif ($tab === 'grn')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="received">Received</option>
                </select>
            @endif

            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <span class="text-gray-400 text-xs">to</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>

            @if ($tab === 'po')
                <button wire:click="exportCsv" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Export CSV
                </button>
            @endif
        </div>
    </div>

    {{-- Tab Content --}}
    @if ($tab === 'pr' && $cpuMode)
        @include('livewire.purchasing.partials.pr-table')
    @elseif ($tab === 'po')
        @include('livewire.purchasing.partials.po-table')
    @elseif ($tab === 'do')
        @include('livewire.purchasing.partials.do-table')
    @elseif ($tab === 'grn')
        @include('livewire.purchasing.partials.grn-table')
    @elseif ($tab === 'sto' && $cpuMode)
        @include('livewire.purchasing.partials.sto-table')
    @endif
</div>
