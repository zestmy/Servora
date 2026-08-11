@php
    /**
     * Needs attention.
     *
     * Previously fixed here: three hand-pasted solid SVGs from a foreign icon
     * family, an off-palette blue for the info tone, and three copies of this
     * markup pasted across the role views. Those are staying fixed.
     *
     * What was left is that the alerts went nowhere. A user read "4 recipe(s)
     * exceed 35% food cost target" and then had to navigate to Recipes and
     * work out which four on their own — the dashboard knew the answer and
     * made them find it again. Three rules existed in total across the whole
     * product, and finance, the role most likely to care that margin had
     * slipped, was handed none of them.
     *
     * Every row now names the thing to do and links to the filtered list that
     * resolves it. That pattern already existed on the chef dashboard's
     * over-cost block, which was the one alert in the product built properly;
     * it generalises to all of them.
     *
     * The stack of full-width tinted blocks does not survive the change. It
     * was tolerable at three; at eight it is a wall of amber that reads as one
     * undifferentiated warning. One card, one row per item, severity carried
     * by a chip and an icon — so the rows stay scannable and the tone stays
     * meaningful.
     *
     * role="status" rather than role="alert": these render on page load and
     * describe standing conditions, so they should be announced when the user
     * arrives at them, not interrupt whatever they are doing.
     *
     * Row shape:
     *   type     'alert' | 'warning' | 'info'
     *   message  the sentence
     *   action   optional link text
     *   href     optional destination — the pre-filtered list, not the module
     */
    $tones = [
        'alert'   => ['badge' => 'badge-danger',  'icon' => 'alert',   'label' => 'Needs attention', 'ink' => 'text-danger-700'],
        'warning' => ['badge' => 'badge-warning', 'icon' => 'warning', 'label' => 'Warning',         'ink' => 'text-warning-700'],
        'info'    => ['badge' => 'badge-brand',   'icon' => 'info',    'label' => 'Note',            'ink' => 'text-brand-700'],
    ];

    // Severity order, so the worst thing is the first thing. The builders
    // append rules in whatever order they happen to compute them, which is not
    // an order anybody wants to read in.
    $rank   = ['alert' => 0, 'warning' => 1, 'info' => 2];
    $sorted = collect($alerts ?? [])
        ->sortBy(fn ($a) => $rank[$a['type'] ?? 'info'] ?? 3)
        ->values();
@endphp

@if ($sorted->isNotEmpty())
    <div class="card mb-6" role="status">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Needs attention</h2>
            <span class="badge-neutral tabular-nums">{{ $sorted->count() }}</span>
        </div>

        <ul class="stack">
            @foreach ($sorted as $alert)
                @php $tone = $tones[$alert['type'] ?? 'info'] ?? $tones['info']; @endphp

                <li class="flex flex-wrap items-center gap-x-3 gap-y-2 px-5 py-3">
                    <x-icon :name="$tone['icon']" size="h-5 w-5" stroke="1.8"
                            class="flex-none {{ $tone['ink'] }}" />

                    <p class="min-w-0 flex-1 text-sm text-gray-800">
                        {{-- The icon is decorative (aria-hidden inside x-icon),
                             so the severity gets a word instead. Colour alone
                             never carries it. --}}
                        <span class="sr-only">{{ $tone['label'] }}:</span>
                        {{ $alert['message'] }}
                    </p>

                    @if (! empty($alert['href']))
                        <a href="{{ $alert['href'] }}" class="btn-secondary btn-sm flex-none">
                            {{ $alert['action'] ?? 'Review' }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@else
    {{-- The absence of problems is information too, and a dashboard that
         renders nothing here leaves the reader unsure whether it checked.
         One quiet line, not a card — this should never compete with the
         figures below it. --}}
    <p class="mb-6 flex items-center gap-2 text-sm text-gray-600">
        <x-icon name="check" size="h-4 w-4" stroke="2.4" class="flex-none text-success-600" />
        Nothing needs your attention right now.
    </p>
@endif
