@props([
    // Optional right-hand affordance — "View all →", a count, a link out.
    'action'     => null,
    'actionHref' => null,

    // Card titles sit under the page's h1. h2 is the honest level for all of
    // them; the `level` escape hatch is for the one case where a title is
    // genuinely subordinate to another title in the same card.
    'level'      => 'h2',
])

{{--
    The heading on a dashboard card.

    There were two treatments for this one tier. `text-sm font-semibold
    text-gray-600` in business, finance, operations, chef and system;
    `text-sm font-semibold text-gray-900` in quick-actions, purchasing and the
    trend chart's figcaption. Both appear on the operations dashboard, in cards
    sitting side by side, where the mismatch is directly visible.

    gray-900 wins. A section title that sits a step behind its own content has
    nothing leading it — which is the argument .page-head already makes about
    page titles, and it applies one level down for the same reason.

    The heading level was the worse half of the problem. Peer cards were
    marked up as h3 in five files and h2 in two, under a page whose title was
    an h2 and whose layout emits no h1 at all — so the heading outline had no
    root and inconsistent branches. The page header now carries the h1 and
    everything here is an h2 unless it is genuinely nested.
--}}

<div {{ $attributes->merge(['class' => 'mb-4 flex items-center justify-between gap-3']) }}>
    <{{ $level }} class="text-sm font-semibold text-gray-900">{{ $slot }}</{{ $level }}>

    @if ($action && $actionHref)
        <a href="{{ $actionHref }}"
           class="flex-none text-xs font-medium text-brand-700 hover:text-brand-800 hover:underline">
            {{ $action }} <span aria-hidden="true">&rarr;</span>
        </a>
    @elseif ($action)
        <span class="flex-none text-xs text-gray-600">{{ $action }}</span>
    @endif
</div>
