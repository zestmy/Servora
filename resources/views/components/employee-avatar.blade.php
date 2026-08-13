@props([
    /** An Employee model, when the caller has one. */
    'employee' => null,
    /*
     * …or the three facts it needs, for callers that do not. The compliance
     * and compensation panels build plain arrays rather than hydrating models,
     * so demanding a model would have meant hydrating one per row to draw a
     * 28px circle.
     */
    'id'    => null,
    'name'  => null,
    'photo' => null,

    /** Tailwind size pair, and the initials that fit inside it. */
    'size'     => 'h-9 w-9',
    'textSize' => 'text-[11px]',
])

@php
    $avatarId    = $id    ?? $employee?->id;
    $avatarName  = $name  ?? $employee?->name;
    $avatarPhoto = $photo ?? $employee?->photo_path;

    // Two letters off the front of the name, the same rule the sidebar and the
    // profile card already use. Deliberately NOT "first initial + surname
    // initial": names here are not reliably given-name-first, so picking which
    // word is the family name would be guessing — the same mistake the
    // dashboard greeting made when it cut at the first space.
    $initials = mb_strtoupper(mb_substr(trim((string) $avatarName), 0, 2));
@endphp

<span {{ $attributes->merge([
        'class' => $size . ' flex-shrink-0 rounded-full overflow-hidden bg-gray-100 '
                 . 'border border-gray-200 flex items-center justify-center',
    ]) }}>
    @if ($avatarPhoto && $avatarId)
        {{-- alt is empty on purpose: the name is right beside it in every
             place this is used, and a screen reader reading it twice is
             noise rather than help. --}}
        <img src="{{ route('hr.employees.photo', $avatarId) }}"
             alt="" loading="lazy"
             class="h-full w-full object-cover" />
    @else
        {{-- gray-600, not gray-400: 2.54:1 on a light surface fails AA and
             even the 3:1 large-text floor. --}}
        <span class="{{ $textSize }} font-semibold text-gray-600 leading-none">{{ $initials }}</span>
    @endif
</span>
