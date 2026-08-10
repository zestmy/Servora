@props(['options', 'current'])

{{-- The named date ranges, drawn the same way everywhere.

     The markup was copied per screen alongside the logic, so the pills had
     already drifted apart in radius and in casing. One component means a
     screen gaining the ranges gains the look with them. --}}
<div class="flex flex-wrap items-center gap-2">
    @foreach ($options as $key => $label)
        <button type="button" wire:click="setQuickRange('{{ $key }}')"
                class="px-3 py-1.5 text-xs font-medium rounded-full border transition
                       {{ $current === $key
                            ? 'bg-brand-600 text-white border-brand-600'
                            : 'bg-white text-gray-600 border-gray-200 hover:border-brand-300 hover:text-brand-700' }}">
            {{ $label }}
        </button>
    @endforeach

    @if ($current === '')
        <span class="badge-neutral">Custom range</span>
    @endif
</div>
