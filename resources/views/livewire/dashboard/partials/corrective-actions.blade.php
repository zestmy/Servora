{{--
    Corrective actions from outlet audits, for anyone who can see audits.

    Counts and the people carrying them, not the rows: the Corrective Actions
    screen has the rows, and every figure here is a link into the filter that
    shows them. Rendered only when there is something to say — the builder
    returns null otherwise, and a card of zeros would compete with the
    figures around it for nothing.
--}}
@php
    $ca = $correctiveActions ?? null;
@endphp

@if ($ca)
    <div class="card mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Corrective actions</h2>
            <a href="{{ route('audits.actions') }}" wire:navigate class="text-xs font-medium text-brand-700 hover:text-brand-800">
                Open the summary <x-icon name="arrow-right" size="h-3.5 w-3.5" class="inline" />
            </a>
        </div>

        <div class="grid grid-cols-2 gap-px bg-gray-100 sm:grid-cols-3 lg:grid-cols-6">
            <a href="{{ route('audits.actions', ['status' => 'outstanding']) }}" wire:navigate class="bg-white p-4 hover:bg-gray-50">
                <p class="stat-label">Outstanding</p>
                <p class="stat-value">{{ $ca['outstanding'] }}</p>
                <p class="stat-meta">{{ $ca['open'] }} open · {{ $ca['in_progress'] }} in progress</p>
            </a>
            <a href="{{ route('audits.actions', ['status' => 'overdue']) }}" wire:navigate class="bg-white p-4 hover:bg-gray-50">
                <p class="stat-label">Overdue</p>
                <p class="stat-value {{ $ca['overdue'] ? 'text-danger-700' : 'text-success-700' }}">{{ $ca['overdue'] }}</p>
                <p class="stat-meta">past their due date</p>
            </a>
            <a href="{{ route('audits.actions', ['status' => 'outstanding']) }}" wire:navigate class="bg-white p-4 hover:bg-gray-50">
                <p class="stat-label">Awaiting verification</p>
                <p class="stat-value {{ $ca['done'] ? 'text-warning-700' : '' }}">{{ $ca['done'] }}</p>
                <p class="stat-meta">marked done by the outlet</p>
            </a>
            <a href="{{ route('audits.actions', ['status' => 'unassigned']) }}" wire:navigate class="bg-white p-4 hover:bg-gray-50">
                <p class="stat-label">No action yet</p>
                <p class="stat-value {{ $ca['unactioned'] ? 'text-warning-700' : '' }}">{{ $ca['unactioned'] }}</p>
                <p class="stat-meta">findings nobody has taken up</p>
            </a>
            <a href="{{ route('audits.actions', ['status' => 'verified']) }}" wire:navigate class="bg-white p-4 hover:bg-gray-50">
                <p class="stat-label">Verified</p>
                <p class="stat-value text-success-700">{{ $ca['verified'] }}</p>
                <p class="stat-meta">{{ $periodCaption ?? 'this period' }}</p>
            </a>
            <div class="bg-white p-4">
                <p class="stat-label">Carrying the most</p>
                @if ($ca['owners']->isEmpty())
                    <p class="stat-meta mt-1">Nothing outstanding.</p>
                @else
                    <ul class="mt-1 space-y-1">
                        @foreach ($ca['owners']->take(3) as $o)
                            <li class="flex items-center justify-between gap-2 text-xs">
                                <span class="min-w-0 truncate text-gray-800">
                                    {{ $o['name'] }}@if ($o['designation']) <span class="text-gray-600">· {{ $o['designation'] }}</span>@endif
                                </span>
                                <span class="flex-none tabular-nums {{ $o['overdue'] ? 'font-medium text-danger-700' : 'text-gray-700' }}">
                                    {{ $o['count'] }}{{ $o['overdue'] ? ' · ' . $o['overdue'] . ' late' : '' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endif
