<div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
    <div>
        <p class="page-eyebrow"><span x-show="presenting" x-cloak>{{ $n }} / {{ count($slides) }} ·</span>Weekly WIP Review</p>
        <h2 class="text-base font-semibold text-gray-800 mt-0.5" :class="presenting && 'text-2xl'">{{ $title }}</h2>
    </div>
    @isset($hint)
        <p class="text-xs text-gray-600">{{ $hint }}</p>
    @endisset
</div>
