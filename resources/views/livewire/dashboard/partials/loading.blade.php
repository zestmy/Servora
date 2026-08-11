{{--
    What the dashboard looks like while it is rebuilding.

    There was no loading state anywhere in the dashboard tree — zero uses of
    wire:loading across nine files. The outlet filter is wire:model.live, so
    changing it re-runs twenty-odd aggregate queries plus a full cost summary,
    and the page simply sat still until they came back. On a slow request the
    natural read is that the click missed, so people change it again.

    A skeleton rather than a spinner: the shape of what is coming is already
    known, so showing it holds the layout still instead of collapsing the page
    and reflowing it a second later. The .skeleton classes have been in the
    stylesheet since the design system landed, used by nothing.

    aria-hidden with a live region beside it — a screen reader wants "Updating
    dashboard", not a description of eight grey rectangles.
--}}

<div class="sr-only" role="status" aria-live="polite">Updating dashboard</div>

<div aria-hidden="true">
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @for ($i = 0; $i < 8; $i++)
            <div class="card stat p-5">
                <span class="skeleton h-3 w-20"></span>
                <span class="skeleton mt-2 h-7 w-24"></span>
                <span class="skeleton mt-2 h-3 w-28"></span>
            </div>
        @endfor
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="card space-y-3 p-6">
            <span class="skeleton h-3 w-32"></span>
            @for ($i = 0; $i < 4; $i++)
                <span class="skeleton h-3 w-full"></span>
            @endfor
        </div>
        <div class="card space-y-3 p-6 lg:col-span-2">
            <span class="skeleton h-3 w-40"></span>
            <span class="skeleton h-44 w-full"></span>
        </div>
    </div>
</div>
