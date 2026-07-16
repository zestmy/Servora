{{--
    Download/export link with busy feedback: on click, swaps its content for a
    spinner + "Preparing…" for a few seconds while the file generates in the
    new tab. Usage:

    <x-download-link href="{{ route(...) }}" class="…existing link classes…">
        <svg …/> Label
    </x-download-link>
--}}
@props(['href'])
<a href="{{ $href }}" target="_blank" rel="noopener"
   x-data="{ busy: false }"
   @click="busy = true; setTimeout(() => busy = false, 6000)"
   {{ $attributes }}>
    <span x-show="!busy" class="inline-flex items-center gap-1.5">{{ $slot }}</span>
    <span x-show="busy" x-cloak class="inline-flex items-center gap-1.5">
        <svg class="animate-spin h-4 w-4 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        Preparing…
    </span>
</a>
