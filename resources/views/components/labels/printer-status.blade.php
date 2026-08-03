@props([
    'printer',
    // Browser-driver printers have no remote state, so they carry a short
    // explanation instead of a badge. Set false in dense lists where the
    // sentence would repeat down every row.
    'explainLocal' => true,
])

{{--
    Whether a printer can be reached.

    Real for the PrintNode driver, which reports a state. Impossible for the
    browser driver — that prints through the chef's own Chrome and nothing
    comes back — so those get a line saying where the job goes rather than a
    status invented to fill the space.

    "Online" is up to a minute old (see PrinterStatus). It is a warning, not a
    guarantee, which is why nothing here gates the print button.
--}}

@php
    $status = app(\App\Services\Labels\PrinterStatus::class)->for($printer);
@endphp

@if ($status === \App\Services\Labels\PrinterStatus::LOCAL)
    @if ($explainLocal)
        <span {{ $attributes->merge(['class' => 'help']) }}>Prints through this device</span>
    @endif
@elseif ($status === \App\Services\Labels\PrinterStatus::ONLINE)
    {{-- No status dot: app.css states the rule for these badges — the colour
         and the word already carry the state, so a dot is one more thing to
         render and nothing more to read. --}}
    <span {{ $attributes->merge(['class' => 'badge-success']) }}>Online</span>
@elseif ($status === \App\Services\Labels\PrinterStatus::OFFLINE)
    <span {{ $attributes->merge(['class' => 'badge-danger']) }}>Offline</span>
@else
    {{-- No key, no link, or PrintNode unreachable. Says so plainly rather
         than implying the printer itself is at fault. --}}
    <span {{ $attributes->merge(['class' => 'badge-neutral']) }} title="Servora could not reach PrintNode to check">
        Status unknown
    </span>
@endif
