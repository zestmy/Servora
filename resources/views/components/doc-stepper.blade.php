@props([
    // Ordered [key => label] list of steps in the document's life
    'steps' => [],
    // Key of the current step
    'current' => null,
    // Terminal failure state label (Rejected / Cancelled) — replaces the trail
    'dead' => null,
])

@php
    $keys = array_keys($steps);
    $currentIdx = array_search($current, $keys);
    if ($currentIdx === false) $currentIdx = -1;
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center flex-wrap gap-y-1']) }}>
    @if ($dead)
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-100 text-red-700 text-xs font-semibold">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ $dead }}
        </span>
    @else
        @foreach ($steps as $key => $label)
            @php
                $idx = array_search($key, $keys);
                $state = $idx < $currentIdx ? 'done' : ($idx === $currentIdx ? 'current' : 'todo');
            @endphp
            @if (! $loop->first)
                <div class="w-5 h-px mx-1 {{ $idx <= $currentIdx ? 'bg-indigo-400' : 'bg-gray-200' }}"></div>
            @endif
            <span class="inline-flex items-center gap-1 text-xs whitespace-nowrap
                {{ $state === 'current' ? 'px-2.5 py-1 rounded-full bg-indigo-600 text-white font-semibold'
                    : ($state === 'done' ? 'text-indigo-600 font-medium' : 'text-gray-400') }}">
                @if ($state === 'done')
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                @endif
                {{ $label }}
            </span>
        @endforeach
    @endif
</div>
