@props([
    'employee',
    /** Tailwind size pair, e.g. 'h-9 w-9'. */
    'size' => 'h-9 w-9',
])

@php
    // Two letters off the front of the name, the same rule the sidebar and the
    // profile card already use. Deliberately NOT "first initial + surname
    // initial": names here are not reliably given-name-first, so picking which
    // word is the family name would be guessing — the same mistake the
    // dashboard greeting made when it cut at the first space.
    $initials = mb_strtoupper(mb_substr(trim((string) $employee->name), 0, 2));
@endphp

<span {{ $attributes->merge([
        'class' => $size . ' flex-shrink-0 rounded-full overflow-hidden bg-gray-100 '
                 . 'border border-gray-200 flex items-center justify-center',
    ]) }}>
    @if ($employee->photo_path)
        {{-- alt is empty on purpose: the name is right beside it in every
             place this is used, and a screen reader reading it twice is
             noise rather than help. --}}
        <img src="{{ route('hr.employees.photo', $employee->id) }}"
             alt="" loading="lazy"
             class="h-full w-full object-cover" />
    @else
        {{-- gray-600, not gray-400: 2.54:1 on a light surface fails AA and
             even the 3:1 large-text floor. --}}
        <span class="text-[11px] font-semibold text-gray-600 leading-none">{{ $initials }}</span>
    @endif
</span>
