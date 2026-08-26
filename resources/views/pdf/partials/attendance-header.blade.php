{{-- Masthead for both attendance exports.

     Required: $docTitle, $brandName, $logoBase64, $from, $to.
     Optional: $outletName, $employmentLabel, $countLabel — the trailing count,
     which reads "40 employee(s)" on the grid and says something different on a
     pool. --}}
@php
    $outletName     = $outletName ?? null;
    $employmentLabel = $employmentLabel ?? null;
    $countLabel     = $countLabel ?? null;
@endphp
    <div class="header">
        <div class="header-left">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" class="logo" />
            @endif
            <span class="brand">{{ $brandName }}</span>
        </div>
        <div class="header-right">
            <div class="title">{{ $docTitle }}</div>
            <div class="meta">
                {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
                @if ($outletName) · {{ $outletName }} @endif
                @if ($employmentLabel) · {{ $employmentLabel }} @endif
                @if ($countLabel) · {{ $countLabel }} @endif
            </div>
        </div>
    </div>
