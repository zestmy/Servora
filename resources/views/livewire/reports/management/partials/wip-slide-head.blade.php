<div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
    <div>
        <p class="page-eyebrow"><span x-show="presenting" x-cloak>{{ $n }} / {{ count($slides) }} · </span>{{ ($report['granularity'] ?? 'week') === 'month' ? 'Monthly' : 'Weekly' }} WIP Review</p>
        <h2 class="text-lg font-bold text-brand-900 mt-0.5" :class="presenting && '!text-3xl'">{{ $title }}</h2>
    </div>
    @isset($hint)
        <p class="text-xs text-gray-600">{{ $hint }}</p>
    @endisset
</div>
