@php
    /**
     * Dashboard alert strip.
     *
     * Was three hand-pasted solid 20px SVGs from a different icon family, and
     * used blue-50 / blue-800 for the info tone — an off-palette hue that
     * exists nowhere else in the product. Both now come from the design
     * system: .alert-* for the surface, <x-icon> for the glyph.
     *
     * The icon is decorative here (aria-hidden inside <x-icon>), so the tone
     * gets a visually-hidden word instead. Colour alone never carries the
     * severity.
     *
     * role="status" rather than role="alert": these render on page load and
     * describe standing conditions, so they should be announced when the user
     * arrives at them, not interrupt whatever they are doing.
     */
    $tones = [
        'warning' => ['class' => 'alert-warning', 'icon' => 'warning', 'label' => 'Warning'],
        'alert'   => ['class' => 'alert-danger',  'icon' => 'alert',   'label' => 'Needs attention'],
        'info'    => ['class' => 'alert-info',    'icon' => 'info',    'label' => 'Note'],
    ];
@endphp

@if (! empty($alerts))
    <div class="mb-6 space-y-2">
        @foreach ($alerts as $alert)
            @php $tone = $tones[$alert['type'] ?? 'info'] ?? $tones['info']; @endphp

            <div class="{{ $tone['class'] }}" role="status">
                <x-icon :name="$tone['icon']" size="h-5 w-5" stroke="1.8" class="mt-px flex-none" />
                <p class="font-medium">
                    <span class="sr-only">{{ $tone['label'] }}:</span>
                    {{ $alert['message'] }}
                </p>
            </div>
        @endforeach
    </div>
@endif
