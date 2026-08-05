{{--
    Settings, grouped by the module a setting belongs to.

    The tiles come from App\Livewire\Settings\Index as data — this file lays
    one out, once. Adding a setting is an array entry there, not twenty lines
    of SVG here.
--}}
@php
    // Each tile's searchable text, precomputed so the filter is a comparison
    // over strings rather than a DOM query.
    $haystackFor = fn (array $tile, array $group) => mb_strtolower(
        $tile['label'] . ' ' . ($tile['note'] ?? '') . ' ' . $group['label']
    );

    $allHaystacks = collect($groups)
        ->flatMap(fn ($g) => collect($g['tiles'])->map(fn ($t) => $haystackFor($t, $g)))
        ->values()
        ->all();
@endphp

<div x-data="{
        q: '',
        /* Client-side: the whole list is already on the page, and filtering on
           the server would be a round trip per keystroke to hide six cards. */
        matches(haystack) {
            const needle = this.q.trim().toLowerCase();
            return needle === '' || haystack.includes(needle);
        },
        get nothingFound() {
            return this.q.trim() !== '' && ! @js($allHaystacks).some(h => this.matches(h));
        }
     }">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h2 class="page-title">Settings</h2>
            <p class="text-xs text-gray-600 mt-1">Grouped by module.</p>
        </div>

        <div class="relative w-full sm:w-72">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="search" x-model="q" placeholder="Search settings…"
                   class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
        </div>
    </div>

    @forelse ($groups as $group)
        @php $groupHaystacks = collect($group['tiles'])->map(fn ($t) => $haystackFor($t, $group))->values()->all(); @endphp

        {{-- The whole group goes when nothing in it matches, so a search never
             leaves a heading standing over an empty grid. --}}
        <section class="mb-8"
                 x-data="{ hs: @js($groupHaystacks) }"
                 x-show="hs.some(h => matches(h))">
            <div class="flex items-baseline gap-3">
                <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ $group['label'] }}</h3>
                <span class="text-xs text-gray-400">{{ count($group['tiles']) }}</span>
            </div>
            @if (! empty($group['note']))
                <p class="text-xs text-gray-500 mt-1">{{ $group['note'] }}</p>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-3">
                @foreach ($group['tiles'] as $tile)
                    @php
                        // Written out rather than composed: Tailwind reads
                        // templates as text and never sees an interpolated class.
                        $chip = ($tile['tone'] ?? 'brand') === 'gray'
                            ? 'bg-gray-100 group-hover:bg-gray-200'
                            : 'bg-brand-50 group-hover:bg-brand-100';
                        $ink = ($tile['tone'] ?? 'brand') === 'gray' ? 'text-gray-600' : 'text-brand-600';
                    @endphp

                    <a href="{{ route($tile['route']) }}"
                       x-show="matches(@js($haystackFor($tile, $group)))"
                       class="group card p-6 hover:border-brand-300 hover:shadow-md transition flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center transition {{ $chip }}">
                            {{-- An icon may hold more than one path; they are stored
                                 separated by " M" and split back out here. --}}
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 {{ $ink }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                @foreach (array_filter(explode(' M', ' ' . ltrim($tile['icon']))) as $segment)
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M{{ trim($segment) }}" />
                                @endforeach
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">{{ $tile['label'] }}</p>
                            @if (! empty($tile['note']))
                                <p class="text-sm text-gray-500 mt-0.5">{{ $tile['note'] }}</p>
                            @endif
                            @if (! empty($tile['count']))
                                <p class="text-xs text-brand-500 font-medium mt-2">
                                    {{ number_format($tile['count'][0]) }} {{ Str::plural($tile['count'][1], $tile['count'][0]) }}
                                </p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <div class="card p-8 text-center text-gray-600">
            There are no settings you can manage.
        </div>
    @endforelse

    <p x-show="nothingFound" x-cloak class="card p-8 text-center text-sm text-gray-600">
        Nothing matches “<span x-text="q"></span>”.
    </p>
</div>
