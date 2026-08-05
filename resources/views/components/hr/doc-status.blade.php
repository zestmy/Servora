@props([
    'held'    => false,
    'expires' => null,
    'yes'     => 'Yes',
    'no'      => 'No',
    'note'    => null,
])

{{--
    One compliance document's state in a table cell: the badge plus the date
    line under it.

    The thresholds are read from DocumentExpiry, so the colours here and the
    counts in the Employees compliance card and the reminder email can never
    tell different stories about the same certificate.
--}}
@php
    $warningDays = \App\Services\Hr\DocumentExpiry::WARNING_DAYS;
    $expired     = $held && $expires && $expires->isBefore(today());
    $expiring    = $held && $expires && ! $expired && $expires->lte(today()->addDays($warningDays));

    $tone = match (true) {
        ! $held   => 'bg-gray-100 text-gray-500',
        $expired  => 'bg-danger-100 text-danger-700',
        $expiring => 'bg-warning-100 text-warning-700',
        default   => 'bg-success-100 text-success-700',
    };
@endphp

<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tone }}">
    {{ $expired ? 'Expired' : ($held ? $yes : $no) }}
</span>

@if ($held && $expires)
    <div class="text-[10px] mt-0.5 whitespace-nowrap {{ $expired ? 'text-danger-500' : ($expiring ? 'text-warning-600' : 'text-gray-600') }}">
        {{ $expired ? 'expired' : 'until' }} {{ $expires->format('d M Y') }}
    </div>
@elseif ($held)
    {{-- No date recorded: neither valid nor expired, and the card counts it
         separately, so it must not silently read as fine here either. --}}
    <div class="text-[10px] text-gray-500 mt-0.5 whitespace-nowrap">no expiry date</div>
@endif

@if ($held && $note)
    <div class="text-[10px] text-gray-600 mt-0.5 font-mono whitespace-nowrap">{{ $note }}</div>
@endif
