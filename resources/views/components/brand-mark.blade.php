{{--
    A company's logo, made legible on whatever it is sitting on.

    The app does not control the artwork. Dark artwork on the LMS's near-black
    sidebar disappears; light artwork on the white login page disappears just
    as completely. Rather than ask every company to maintain two variants,
    CompanyLogo measures the pixels and this component reacts: when the logo
    and the surface are the same polarity, it puts the logo on a contrasting
    chip. When they already contrast, it renders the logo bare — a chip that
    isn't needed is visual noise.

    Props:
      company  the Company (or null — renders nothing, callers keep their fallback)
      surface  'dark' or 'light', what the logo is being placed on
      size     tailwind height class for the image, e.g. 'h-10'
      width    tailwind max-width class, e.g. 'max-w-[180px]'
--}}
@props([
    'company' => null,
    'surface' => 'light',
    'size'    => 'h-10',
    'width'   => 'max-w-[160px]',
    'alt'     => null,
])

@php
    $service = app(\App\Services\Branding\CompanyLogo::class);
    $src     = $service->url($company?->id);

    // null means "couldn't tell" — leave it bare rather than guess a chip on.
    $logoIsDark = $src ? $service->isDark($company?->id) : null;
    $onDark     = $surface === 'dark';
    $needsChip  = $logoIsDark !== null && $logoIsDark === $onDark;

    $chip = $onDark
        ? 'bg-white ring-1 ring-black/5'
        : 'bg-gray-900 ring-1 ring-white/10';
@endphp

@if ($src)
    @if ($needsChip)
        <span class="inline-flex shrink-0 items-center justify-center rounded-lg p-1.5 {{ $chip }}">
            <img src="{{ $src }}" alt="{{ $alt ?? $company?->brand_name ?? $company?->name }}"
                 class="{{ $size }} {{ $width }} w-auto object-contain">
        </span>
    @else
        <img src="{{ $src }}" alt="{{ $alt ?? $company?->brand_name ?? $company?->name }}"
             class="shrink-0 {{ $size }} {{ $width }} w-auto object-contain">
    @endif
@endif
