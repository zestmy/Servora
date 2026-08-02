@props([
    'title',
    'eyebrow'  => null,
    'subtitle' => null,
])

{{--
    The block every product screen opens with.

    It existed 85 times as a hand-written `<h2 class="text-lg font-semibold
    text-gray-700">` with a flex row of buttons beside it. Two problems with
    that heading: it was the same weight as the card headings below it, and
    gray-700 put it a step behind its own content — so the page had no focal
    point and every screen drifted a little from the last.

    Usage:

        <x-page-header title="Print labels" eyebrow="Labels">
            <x-slot:actions>
                <a href="…" class="btn-secondary">Print sets</a>
            </x-slot:actions>
        </x-page-header>
--}}

<div {{ $attributes->merge(['class' => 'page-head']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="page-eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 class="page-title {{ $eyebrow ? 'mt-1' : '' }}">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="page-actions">{{ $actions }}</div>
    @endisset
</div>
