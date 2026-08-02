@props([
    // When the item was made / printed. Without it there is no window to
    // measure against, so the bar is dropped and only the readout renders.
    'start' => null,
    // The use-by. Required — with nothing to count down to there is nothing
    // to draw.
    'end' => null,
    // The two halves can be rendered separately — bar full-width across a
    // card, readout inline on a row with the absolute date. Turning one off
    // is how you place them apart without nesting the component in itself.
    'bar'  => true,
    'time' => true,
])

{{--
    Shelf-life meter.

    The one question anyone asks of a labelled item is how long it has left,
    and before this the answer was re-derived on every screen that needed it:
    the expiring list did its own colour thresholds, the print queue showed a
    bare date, the staff app showed a different bare date. Same question,
    three answers, none of them comparable at a glance.

    Thresholds, and why:

      over   now is past the use-by. Not "nearly" — this is a discard.
      soon   the last quarter of the item's own window, capped so it can
             never fire more than three days ahead.

             Proportional rather than absolute, because the windows in one
             kitchen span three orders of magnitude: cut garnish lives 12
             hours, a chutney lives 30 days. A flat "warn under 24 hours"
             paints every same-day prep item amber the moment it is
             printed — which is most of them — and says nothing at all
             about the chutney. A quarter of the window lands at 3 hours
             on the garnish and 18 hours on a three-day stock, which is
             when someone can still act on it.

             The three-day cap is what stops the chutney going amber a
             week out and training everyone to ignore the colour.
      fresh  everything else.

      With no start there is no window, so nothing proportional can be
      computed and it falls back to a flat 24 hours.

    The bar shows time REMAINING, so it drains left-to-right as the item
    ages. A filling bar would read as progress toward something good.
--}}

@php
    $endAt = $end ? \Illuminate\Support\Carbon::parse($end) : null;
@endphp

@if ($endAt)
    @php
        $startAt = $start ? \Illuminate\Support\Carbon::parse($start) : null;
        $now     = now();

        $secondsLeft = $now->diffInSeconds($endAt, false);
        $isOver      = $secondsLeft <= 0;

        // Fraction of the window still to run. Null when there is no start,
        // which is what suppresses the bar.
        $fraction = null;
        if ($startAt && $endAt->greaterThan($startAt)) {
            $window   = $startAt->diffInSeconds($endAt);
            $fraction = max(0, min(1, $secondsLeft / $window));
        }

        $state = match (true) {
            $isOver => 'over',
            // Last quarter of its own window, but never more than 3 days out.
            $fraction !== null => ($fraction <= 0.25 && $secondsLeft <= 259200) ? 'soon' : 'fresh',
            // No window to measure against — flat fallback.
            default => $secondsLeft <= 86400 ? 'soon' : 'fresh',
        };

        // Compact readout. Two units at most — "2d 4h" is scannable in a
        // column, "2 days 4 hours 11 minutes" is not.
        // Round before intdiv: diffInSeconds returns a float on Carbon 3, so
        // truncating shows a 12-hour window as "11h 59m" from the first
        // second. (It is also a deprecation notice on PHP 8.2.)
        $abs = (int) round(abs($secondsLeft));
        $d   = intdiv($abs, 86400);
        $h   = intdiv($abs % 86400, 3600);
        $m   = intdiv($abs % 3600, 60);

        $span = match (true) {
            $d > 0  => $d . 'd' . ($h > 0 ? ' ' . $h . 'h' : ''),
            $h > 0  => $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : ''),
            default => max(1, $m) . 'm',
        };

        $readout = $isOver ? $span . ' over' : $span . ' left';

        // An expired bar sits full on a red track: the item has run its whole
        // window and then some, and an empty bar would read as "no data".
        $width = $isOver ? 100 : ($fraction !== null ? round($fraction * 100, 1) : null);
    @endphp

    @php $showBar = $bar && $width !== null; @endphp

    <div {{ $attributes->merge(['class' => 'meter-' . $state]) }}>
        @if ($showBar)
            <span class="meter" role="img"
                  aria-label="{{ $isOver ? 'Expired ' . $span . ' ago' : $span . ' of shelf life remaining' }}">
                <span class="meter-fill" style="width: {{ $width }}%"></span>
            </span>
        @endif

        @if ($time)
            <span class="meter-time {{ $showBar ? 'mt-1.5 block' : '' }}">{{ $readout }}</span>
        @endif
    </div>
@endif
