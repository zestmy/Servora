<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 px-4 py-3 bg-success-50 border border-success-200 text-success-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <x-page-header title="Asset Records" eyebrow="Assets"
                   subtitle="Counts, receipts and disposals — everything that moves the asset register.">
        <x-slot:actions>
            <a href="{{ route('assets.register') }}" wire:navigate class="btn-secondary">Register</a>
            @canDo('assets.movements.record')
                <a href="{{ route('assets.movements.create', ['type' => 'disposal']) }}" wire:navigate class="btn-secondary">
                    + Disposal
                </a>
                <a href="{{ route('assets.movements.create', ['type' => 'receipt']) }}" wire:navigate class="btn-secondary">
                    + Receipt
                </a>
            @endcanDo
            @canDo('assets.counts.record')
                <a href="{{ route('assets.counts.create') }}" wire:navigate class="btn-primary">+ Count</a>
            @endcanDo
        </x-slot:actions>
    </x-page-header>

    {{-- Tabs --}}
    <div class="seg mb-4">
        @foreach ($tabs as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')"
                    class="seg-item {{ $tab === $key ? 'seg-item-on' : '' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="toolbar mb-4">
        <div class="w-full">
            <x-quick-ranges :options="$quickRangeOptions" :current="$quickRange" />
        </div>

        <div class="flex w-full flex-col flex-wrap gap-3 sm:flex-row sm:items-center">
            <div class="flex-1 min-w-[180px]">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search reference or notes…" class="input" />
            </div>

            <input type="date" wire:model.live="dateFrom" class="input sm:w-40" />
            <input type="date" wire:model.live="dateTo" class="input sm:w-40" />

            @if ($outlets->isNotEmpty())
                <select wire:model.live="outletFilter" class="input sm:w-48">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @endif

            @if ($config['status'])
                <select wire:model.live="statusFilter" class="input sm:w-40">
                    <option value="">Any status</option>
                    <option value="draft">Draft</option>
                    <option value="completed">Completed</option>
                </select>
            @endif

            @if ($config['supplier'])
                <select wire:model.live="supplierFilter" class="input sm:w-48">
                    <option value="">Any supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            @endif

            <button wire:click="resetFilters" class="btn-ghost">Reset</button>
        </div>
    </div>

    <div class="stat mb-4">
        <p class="stat-label">{{ $config['money'] }} · {{ $rangeLabel }}</p>
        <p class="stat-value">{{ number_format($rangeTotal, 2) }}</p>
        <p class="stat-meta">{{ $records->total() }} record{{ $records->total() === 1 ? '' : 's' }}</p>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[820px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left w-32">Date</th>
                        <th class="px-4 py-3 text-left">Reference</th>
                        <th class="px-4 py-3 text-left w-44">Outlet</th>
                        <th class="px-4 py-3 text-left w-40">
                            {{ $config['supplier'] ? 'Supplier' : ($tab === 'disposals' ? 'Reason' : 'Department') }}
                        </th>
                        <th class="px-4 py-3 text-center w-20">Items</th>
                        <th class="px-4 py-3 text-right w-36">{{ $config['money'] }}</th>
                        @if ($config['status'])
                            <th class="px-4 py-3 text-center w-28">Status</th>
                        @endif
                        <th class="px-4 py-3 text-center w-28">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php
                            $isCount = $tab === 'counts';
                            $date    = $isCount ? $record->count_date : $record->movement_date;
                            $amount  = $isCount ? $record->total_asset_value : $record->total_cost;
                            $href    = $isCount
                                ? route('assets.counts.show', $record->id)
                                : route('assets.movements.show', $record->id);
                            /*
                             * Both destinations are forms, and a form needs the
                             * ability to record — the same split the stock-take
                             * routes have always had. `assets.view` reads this
                             * list and the register; it does not open a
                             * document for editing, so it must not be offered a
                             * link that 403s.
                             */
                            $canOpen = auth()->user()?->canDo(
                                $isCount ? 'assets.counts.record' : 'assets.movements.record'
                            );
                        @endphp
                        <tr wire:key="rec-{{ $tab }}-{{ $record->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-600">{{ $date?->format('d M Y') }}</td>

                            <td class="px-4 py-3">
                                @if ($canOpen)
                                    <a href="{{ $href }}" wire:navigate class="font-medium text-brand-700 hover:underline">
                                        {{ $record->reference_number ?: '#' . $record->id }}
                                    </a>
                                @else
                                    <span class="font-medium text-gray-700">{{ $record->reference_number ?: '#' . $record->id }}</span>
                                @endif
                                @if ($record->notes)
                                    <span class="block text-xs text-gray-600 truncate max-w-xs">{{ $record->notes }}</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-gray-600">{{ $record->outlet?->name ?? '—' }}</td>

                            <td class="px-4 py-3 text-gray-600">
                                @if ($config['supplier'])
                                    {{ $record->supplier?->name ?? '—' }}
                                @elseif ($tab === 'disposals')
                                    {{ $record->reasonLabel() ?? '—' }}
                                @else
                                    {{ $record->department?->name ?? '—' }}
                                @endif
                            </td>

                            <td class="px-4 py-3 text-center text-gray-600 tabular-nums">{{ $record->lines_count }}</td>

                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-800">
                                {{ number_format((float) $amount, 2) }}
                            </td>

                            @if ($config['status'])
                                <td class="px-4 py-3 text-center">
                                    <span class="{{ $record->status === 'completed' ? 'badge-success' : 'badge-warning' }}">
                                        {{ ucfirst($record->status) }}
                                    </span>
                                </td>
                            @endif

                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    @if ($canOpen)
                                        <a href="{{ $href }}" wire:navigate class="icon-btn" title="Open">
                                            <x-icon name="chevron-right" size="h-4 w-4" />
                                        </a>
                                    @endif
                                    @if ($isCount)
                                        {{-- Printing is reading, so it is offered to
                                             anyone who can open this list — unlike the
                                             chevron beside it, which leads to a form. --}}
                                        <a href="{{ route('assets.counts.count-sheet', $record->id) }}"
                                           target="_blank" class="icon-btn" title="Print count sheet">
                                            <x-icon name="printer" size="h-4 w-4" />
                                        </a>
                                        @canDo('assets.counts.delete')
                                            <button wire:click="deleteCount({{ $record->id }})"
                                                    class="icon-btn icon-btn-danger" title="Delete"
                                                    data-confirm-delete="Delete this count. The register falls back to the count before it, which may change what this outlet is valued at.">
                                                <x-icon name="trash" size="h-4 w-4" />
                                            </button>
                                        @endcanDo
                                    @else
                                        @canDo('assets.movements.delete')
                                            <button wire:click="deleteMovement({{ $record->id }})"
                                                    class="icon-btn icon-btn-danger" title="Delete"
                                                    data-confirm-delete="Delete this {{ strtolower($record->typeLabel()) }}. The register stops counting it.">
                                                <x-icon name="trash" size="h-4 w-4" />
                                            </button>
                                        @endcanDo
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $config['status'] ? 8 : 7 }}" class="px-4 py-10">
                                <div class="empty-state">
                                    <p class="empty-title">Nothing here {{ $rangeLabel }}</p>
                                    <p class="empty-body">
                                        @if ($tab === 'counts')
                                            A completed count sets what each asset is worth at an outlet from then on.
                                        @elseif ($tab === 'receipts')
                                            Record what arrived, and the register counts it from that date.
                                        @else
                                            Record what was broken, lost or written off, so the register stops counting it.
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $records->links() }}</div>
        @endif
    </div>
</div>
