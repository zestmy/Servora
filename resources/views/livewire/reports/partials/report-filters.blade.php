{{-- Standard report filters bar --}}
<div class="card p-4 mb-4">
    <div class="flex flex-col sm:flex-row flex-wrap gap-3">
        <div class="flex items-center gap-1">
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
            <span class="text-gray-600 text-xs">to</span>
            <input type="date" wire:model.live="dateTo" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
        </div>
        @if ($showOutlet ?? true)
            <select wire:model.live="outletFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">All Outlets</option>
                @foreach ($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
        @endif
        @if ($showSupplier ?? false)
            <select wire:model.live="supplierFilter" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">All Suppliers</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        @endif
        @if (isset($exportAction))
            <button wire:click="{{ $exportAction }}" class="btn-secondary ml-auto">
                Export CSV
            </button>
        @endif
    </div>
</div>
