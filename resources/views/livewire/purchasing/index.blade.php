<div>
    {{-- Flash. Actions with follow-up guidance render a PERSISTENT dismissible
         panel (a 3-second toast is too easy to miss when documents move
         between tabs); plain successes keep the auto-hiding toast. --}}
    @if (session()->has('success'))
        @if (session()->has('success_next'))
            <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show"
                 class="mb-4 px-4 py-3 bg-success-50 border border-success-200 rounded-lg flex items-start justify-between gap-3">
                <div class="text-sm">
                    <p class="text-success-700 font-medium">{{ session('success') }}</p>
                    <p class="text-success-700/70 text-xs mt-1">
                        <span class="font-semibold uppercase tracking-wider text-[10px] text-success-600">What happens next</span><br>
                        {{ session('success_next') }}
                    </p>
                </div>
                <button @click="show = false" title="Dismiss" class="flex-shrink-0 text-success-400 hover:text-success-600 p-1">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @else
            <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                 class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
                {{ session('success') }}
            </div>
        @endif
    @endif
    @if (session()->has('error'))
        <div class="mb-4 px-4 py-3 bg-danger-50 border border-danger-200 text-danger-700 text-sm rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h2 class="page-title">Purchasing</h2>
        <div class="flex flex-wrap gap-2">
            @if ($canCreatePr)
                <a href="{{ route('purchasing.requests.create') }}"
                   class="px-3 md:px-4 py-2 {{ $canCreatePo ? 'bg-white text-brand-600 border border-brand-200 hover:bg-brand-50' : 'bg-brand-600 text-white hover:bg-brand-700' }} text-sm font-medium rounded-lg transition">
                    <span class="sm:hidden">+ PR</span>
                    <span class="hidden sm:inline">+ Purchase Request</span>
                </a>
            @endif
            @if ($canCreatePo)
                <a href="{{ route('purchasing.orders.create') }}"
                   class="btn-primary">
                    <span class="sm:hidden">+ PO</span>
                    <span class="hidden sm:inline">+ New Purchase Order</span>
                </a>
            @endif
            @if ($cpuMode && $isCpuUser)
                <a href="{{ route('purchasing.consolidate') }}"
                   class="px-3 md:px-4 py-2 bg-white text-brand-600 text-sm font-medium rounded-lg border border-brand-200 hover:bg-brand-50 transition">
                    <span class="sm:hidden">Consolidate</span>
                    <span class="hidden sm:inline">Consolidate PRs</span>
                </a>
                <a href="{{ route('purchasing.transfers.create') }}"
                   class="px-3 md:px-4 py-2 bg-white text-brand-600 text-sm font-medium rounded-lg border border-brand-200 hover:bg-brand-50 transition">
                    <span class="sm:hidden">+ Transfer</span>
                    <span class="hidden sm:inline">+ Stock Transfer</span>
                </a>
            @endif
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        @foreach ($stats as $stat)
            <div class="card p-5">
                <p class="text-xs text-gray-600 uppercase tracking-wider">{{ $stat['label'] }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-4">
        <nav class="flex gap-4 -mb-px flex-wrap">
            @if ($showPrTab)
                <button wire:click="$set('tab', 'pr')"
                        title="Purchase Requests — what your outlet needs, sent for approval before ordering"
                        class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'pr' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Requests (PR)
                </button>
            @endif
            @if ($showPoTab)
                <button wire:click="$set('tab', 'po')"
                        title="Purchase Orders — confirmed orders sent to suppliers"
                        class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'po' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Orders (PO)
                </button>
            @endif
            @if ($showDoTab)
                <button wire:click="$set('tab', 'do')"
                        title="Delivery Orders — deliveries that arrived against an order"
                        class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'do' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Deliveries (DO)
                </button>
            @endif
            @if ($showGrnTab)
                <button wire:click="$set('tab', 'grn')"
                        title="Goods Received Notes — confirm what actually arrived and in what condition"
                        class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'grn' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Goods Received (GRN)
                </button>
            @endif
            @if ($showStoTab)
                <button wire:click="$set('tab', 'sto')"
                        title="Stock Transfers — goods moving from central purchasing to outlets"
                        class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ $tab === 'sto' ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Transfers (STO)
                </button>
            @endif
            @if ($showInvoiceTab)
                <a href="{{ route('purchasing.invoices.index') }}"
                   class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ request()->routeIs('purchasing.invoices.*') ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Invoices
                </a>
            @endif
            @if ($showCnTab)
                <a href="{{ route('purchasing.credit-notes.index') }}"
                   class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ request()->routeIs('purchasing.credit-notes.*') ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Credit Notes
                </a>
            @endif
            @if ($showSupplierTab)
                <a href="{{ route('purchasing.suppliers.directory') }}"
                   class="pb-3 px-1 text-sm font-medium border-b-2 transition {{ request()->routeIs('purchasing.suppliers.*') ? 'border-brand-500 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    Suppliers
                </a>
            @endif
        </nav>
    </div>

    {{-- Plain-language explainer for the active tab --}}
    @php
        $tabHelp = [
            'pr'  => 'A Purchase Request (PR) is your outlet\'s shopping list — what you need, sent for approval before anything is ordered.',
            'po'  => 'A Purchase Order (PO) is the confirmed order sent to a supplier. Once approved, goods are delivered against it.',
            'do'  => 'A Delivery Order (DO) records a delivery that arrived from a supplier against an order.',
            'grn' => 'A Goods Received Note (GRN) confirms what was actually received — quantities and condition — and updates your stock costs.',
            'sto' => 'A Stock Transfer (STO) moves goods from central purchasing to an outlet.',
        ];
    @endphp
    @if (isset($tabHelp[$tab]))
        <p class="text-xs text-gray-600 -mt-1 mb-4">{{ $tabHelp[$tab] }}</p>
    @endif

    {{-- Filters --}}
    <div class="card p-4 mb-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-48">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search..."
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            </div>

            @if ($multiOutlet)
                <select wire:model.live="outletFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @endif

            @if ($tab === 'sto')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            @elseif ($tab === 'pr')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Pending Approval</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="converted">Converted to PO</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            @elseif ($tab === 'po')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Pending Approval</option>
                    <option value="approved">Approved</option>
                    <option value="sent">Processing</option>
                    <option value="partial">Partially Received</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select wire:model.live="supplierFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Suppliers</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            @elseif ($tab === 'do')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="received">Received</option>
                </select>
            @elseif ($tab === 'grn')
                <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="received">Received</option>
                </select>
            @endif

            <div class="flex items-center gap-1">
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <span class="text-gray-600 text-xs">to</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            </div>

            @if ($tab === 'po')
                <button wire:click="exportCsv" class="btn-secondary">
                    Export CSV
                </button>
            @endif
        </div>
    </div>

    {{-- Tab Content --}}
    @if ($tab === 'pr')
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
