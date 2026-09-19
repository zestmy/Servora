<div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
    <div>
        <p class="page-eyebrow"><span class="wip-slide-count">{{ $n }} / {{ count($slides) }} · </span>{{ ($report['granularity'] ?? 'week') === 'month' ? 'Monthly' : 'Weekly' }} WIP Review</p>
        <h2 class="wip-slide-title text-lg font-bold text-brand-900 mt-0.5">{{ $title }}</h2>
    </div>
    @isset($hint)
        <p class="wip-hint text-xs text-gray-600">{{ $hint }}</p>
    @endisset
</div>
