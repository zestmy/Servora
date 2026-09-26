{{--
    Audit scores, per outlet, over the last twelve months.

    Sorted worst first, because the outlet that needs attention is the one
    the reader is looking for. The sparkline is the shape of the series, not
    a reading of it — the figure beside it is the reading; the full chart is
    one click away on the trend report. Rendered only when at least one
    audit has been submitted in the window.
--}}
@php
    $t = $auditTrend ?? null;
    $ink = ['good' => 'text-success-700', 'fair' => 'text-warning-700', 'poor' => 'text-danger-700', 'none' => 'text-gray-500'];
@endphp

@if ($t)
    <div class="card mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <h2 class="text-sm font-semibold text-gray-900">Audit scores</h2>
                <span class="text-xs text-gray-600">
                    last {{ $t['months'] }} months · {{ $t['count'] }} audit{{ $t['count'] === 1 ? '' : 's' }}
                    · average <span class="font-medium tabular-nums {{ $ink[\App\Services\Audits\AuditScoreService::band($t['average'])] }}">{{ number_format($t['average'], 1) }}%</span>
                    · pass rate <span class="font-medium tabular-nums {{ $t['passRate'] >= 80 ? 'text-success-700' : 'text-gray-800' }}">{{ $t['passRate'] }}%</span>
                </span>
            </div>
            <a href="{{ route('reports.audit-trend') }}" wire:navigate class="text-xs font-medium text-brand-700 hover:text-brand-800">
                Full trend <x-icon name="arrow-right" size="h-3.5 w-3.5" class="inline" />
            </a>
        </div>

        <ul class="stack">
            @foreach ($t['outlets'] as $o)
                @php $band = \App\Services\Audits\AuditScoreService::band($o['latest']); @endphp
                <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('audits.show', $o['id']) }}" wire:navigate class="text-sm font-medium text-gray-900 hover:text-brand-700">{{ $o['name'] }}</a>
                        <p class="text-xs text-gray-600">
                            {{ $o['latestOn']->format('d M Y') }} · {{ $o['count'] }} audit{{ $o['count'] === 1 ? '' : 's' }} in the window
                        </p>
                    </div>

                    @if ($o['outcome'])
                        <span @class([
                            'flex-none',
                            'badge-success' => $o['outcome'] === 'pass',
                            'badge-warning' => $o['outcome'] === 'conditional',
                            'badge-danger'  => $o['outcome'] === 'fail',
                        ])>{{ $o['label'] }}</span>
                    @endif

                    <div class="flex flex-none items-baseline gap-2">
                        <span class="text-lg font-semibold tabular-nums {{ $ink[$band] }}">{{ number_format($o['latest'], 1) }}%</span>
                        <span class="text-xs tabular-nums {{ $o['delta'] === null ? 'text-gray-500' : ($o['delta'] > 0 ? 'text-success-700' : ($o['delta'] < 0 ? 'text-danger-700' : 'text-gray-600')) }}">
                            {{ $o['delta'] === null ? 'first audit' : (($o['delta'] > 0 ? '+' : '') . number_format($o['delta'], 1) . ' vs previous') }}
                        </span>
                    </div>

                    <div class="flex-none"><x-sparkline :values="$o['series']" /></div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
