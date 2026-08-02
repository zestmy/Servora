<div>
    <div class="mb-6">
        <p class="page-eyebrow">Labels / Print log</p>
        <h1 class="page-title mt-1">Print log</h1>
    </div>

    <div class="card p-5 mb-4">
        <p class="text-sm text-gray-600">
            Every label printed, grouped by print run. This is the record to show an auditor.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            Each line shows what was printed at the time, not what the item says now — renaming a recipe or
            changing its shelf life doesn't rewrite history.
        </p>
        <p class="text-xs text-gray-500 mt-2">
            <strong>Sent</strong> is as much as browser printing can ever report — nothing comes back from a
            browser. PrintNode jobs start <strong>Queued</strong> and settle to <strong>Printed</strong> or
            <strong>Failed</strong> within about ten minutes.
        </p>
    </div>

    <div class="card p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Outlet</label>
                <select wire:model.live="outletId" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All outlets</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                <input type="date" wire:model.live="from" class="rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                <input type="date" wire:model.live="to" class="rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Print set</label>
                <select wire:model.live="setFilter" class="rounded-lg border-gray-300 text-sm">
                    <option value="">All sets</option>
                    @foreach ($sets as $set)
                        <option value="{{ $set->id }}">{{ $set->name }}</option>
                    @endforeach
                    <option value="none">Ad-hoc (no set)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Group by</label>
                <div class="seg">
                    @foreach (['date' => 'Date', 'set' => 'Print set'] as $mode => $label)
                        <button type="button" wire:click="$set('groupBy', '{{ $mode }}')"
                                class="seg-item {{ $groupBy === $mode ? 'seg-item-on' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-surface min-w-[760px]">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Printed</th>
                        <th class="px-4 py-3 text-left">Set</th>
                        <th class="px-4 py-3 text-left">Outlet</th>
                        <th class="px-4 py-3 text-left">Prepared by</th>
                        <th class="px-4 py-3 text-center w-24">Items</th>
                        <th class="px-4 py-3 text-center w-24">Labels</th>
                        <th class="px-4 py-3 text-center w-20"></th>
                    </tr>
                </thead>
                <tbody>
                    @php $lastSetKey = null; @endphp
                    @forelse ($batches as $batch)
                        @if ($groupBy === 'set')
                            @php $setKey = $batch->label_set_id ?? 'none'; @endphp
                            @if ($setKey !== $lastSetKey)
                                @php $lastSetKey = $setKey; @endphp
                                {{-- Repeats at the top of a page when a group spans one. --}}
                                <tr class="bg-gray-50">
                                    <td colspan="7" class="px-4 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        {{ $batch->labelSet?->name ?? 'Ad-hoc (no set)' }}
                                    </td>
                                </tr>
                            @endif
                        @endif

                        <tr class="hover:bg-gray-50" wire:key="batch-{{ $batch->id }}">
                            <td class="px-4 py-3 text-gray-700">{{ $batch->printed_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $batch->labelSet?->name ?? 'Ad-hoc' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $batch->outlet?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $batch->employee?->name ?? '—' }}
                                @if ($batch->user && $batch->employee?->name !== $batch->user->name)
                                    <span class="text-xs text-gray-600 block">login: {{ $batch->user->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">{{ $batch->item_count }}</td>
                            <td class="px-4 py-3 text-center font-medium text-gray-700">{{ $batch->label_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggle({{ $batch->id }})" class="text-xs text-brand-600 hover:underline">
                                    {{ $expandedId === $batch->id ? 'Hide' : 'View' }}
                                </button>
                            </td>
                        </tr>

                        @if ($expandedId === $batch->id && $expanded)
                            <tr class="bg-gray-50/60">
                                <td colspan="7" class="px-4 py-3">
                                    <table class="table-surface text-xs">
                                        <thead>
                                            <tr>
                                                <th class="py-1 text-left">Item</th>
                                                <th class="py-1 text-left">Label</th>
                                                <th class="py-1 text-left">Storage</th>
                                                <th class="py-1 text-left">Start</th>
                                                <th class="py-1 text-left">Use by</th>
                                                <th class="py-1 text-center">Copies</th>
                                                <th class="py-1 text-center">Outcome</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-gray-600">
                                            @foreach ($expanded->prints as $print)
                                                <tr wire:key="print-{{ $print->id }}">
                                                    <td class="py-1 font-medium text-gray-700">{{ $print->printedName() }}</td>
                                                    <td class="py-1">{{ \App\Models\LabelTemplate::LABEL_TYPES[$print->label_type] ?? $print->label_type }}</td>
                                                    <td class="py-1">{{ \App\Models\ShelfLifeRule::stateLabel($print->storage_state) }}</td>
                                                    <td class="py-1">{{ $print->start_at?->format('d/m/Y H:i') }}</td>
                                                    <td class="py-1">
                                                        {{ $print->end_at?->format('d/m/Y H:i') ?? '—' }}
                                                        @if ($print->manual_expiry)
                                                            <span class="ml-1 px-1 py-0.5 rounded bg-warning-50 text-warning-700 text-[10px]">manual</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-1 text-center">{{ $print->copies }}</td>
                                                    <td class="py-1 text-center">
                                                        @php
                                                            $tone = match ($print->status) {
                                                                'done'             => 'bg-success-50 text-success-700',
                                                                'error', 'expired' => 'bg-danger-50 text-danger-700',
                                                                'queued'           => 'bg-warning-50 text-warning-700',
                                                                default            => 'bg-gray-100 text-gray-500',
                                                            };
                                                        @endphp
                                                        <span class="px-1.5 py-0.5 rounded text-[10px] {{ $tone }}">
                                                            {{ $print->statusLabel() }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-600 text-sm">
                                No labels printed in this range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $batches->links() }}</div>
</div>
