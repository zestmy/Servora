{{--
    A staff member's initials in a coloured disc.

    INITIALS RATHER THAN THE PHOTOGRAPH ON FILE, and that is a decision rather
    than a shortcut. Two reasons, either sufficient. Only 18 of 57 active staff
    have a photo on record, so a photo-led avatar leaves two thirds of the board
    falling back to this anyway — and the photos are HR documents held on the
    PRIVATE disk behind an `hr.view` gate, so putting them on a leaderboard that
    every PIN session can open is a privacy decision for the merchant to make,
    not one to arrive at by way of a UI tweak.

    THE COLOUR IS DERIVED FROM THE NAME, so the same person is the same colour
    every time. On a board you scan rather than read, a stable colour is what
    lets somebody find their own row before they have read a single character —
    which is the entire job of an avatar at this size.

    Every pair below is a 100-level tint under 800-level text: comfortably past
    AA at this size, and checked as a family rather than picked individually so
    no one row is quietly the unreadable one.

    Props:
      name  the staff member's name — initials and colour both come from it
      size  tailwind sizing classes for the disc
--}}
@props([
    'name' => '',
    'size' => 'h-9 w-9 text-xs',
    /*
     * The photograph, when there is one and the caller has it.
     *
     * The boards were built on initials alone — see the note above, which is
     * still the reason a NAME is enough to draw one. The merchant asked for the
     * real faces, so a caller that has the employee's id and photo_path may
     * hand them over and get the photograph instead; everyone without one still
     * falls back to their coloured disc, which is most of the floor.
     *
     * Served by clock.staff.photo, not the manager's route: that one is gated
     * on hr.view, and this is drawn inside a PIN session. See
     * StaffPhotoController for what the staff-facing route does and does not
     * expose.
     */
    'employee' => null,
    'photo'    => null,
    /*
     * Which route serves the file, because the same board is drawn in two apps
     * whose photo routes are gated differently. The Staff Portal has
     * clock.staff.photo (a PIN for this company); the manager app has
     * hr.employees.photo (hr.view plus outlet access) and does not have the
     * staff route bound at all — it lives on the subdomain. A caller in the
     * manager app passes its own route AND checks the ability first, or the
     * board fills with 403s wearing broken-image icons.
     */
    'photoRoute' => 'clock.staff.photo',
])

@php
    $parts = array_values(array_filter(preg_split('/\s+/', trim((string) $name)) ?: []));

    // Two letters where there are two words, otherwise the first two of the
    // only one: a single glyph in a disc sized for a pair reads as a rendering
    // fault rather than as a name.
    $initials = count($parts) >= 2
        ? mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1))
        : mb_strtoupper(mb_substr((string) $name, 0, 2));

    $tones = [
        'bg-brand-100 text-brand-800',
        'bg-info-100 text-info-800',
        'bg-success-100 text-success-800',
        'bg-warning-100 text-warning-800',
        'bg-danger-100 text-danger-800',
        'bg-gray-200 text-gray-800',
    ];

    // crc32 over the name, not array_rand: the colour has to survive a page
    // reload, a re-sort and a different screen, or it is noise rather than a
    // handle.
    $tone = $tones[crc32(mb_strtolower(trim((string) $name))) % count($tones)];
@endphp

{{-- ONE ELEMENT, with the photograph laid over the disc rather than instead of
     it. A photo that 404s — deleted from the disk, or a path left behind by a
     restore — then degrades to the initials underneath instead of to a broken
     image icon, which is the failure a board full of faces would otherwise
     show. Most of the floor has no photo at all and never reaches the img. --}}
<span aria-hidden="true"
      {{ $attributes->merge(['class' => "relative inline-flex shrink-0 items-center justify-center rounded-full font-semibold tracking-wide {$size} {$tone}"]) }}>
    {{ $initials }}

    @if ($employee && $photo)
        {{-- alt is empty on purpose: the name is beside it everywhere this is
             used, and a screen reader reading it twice is noise. --}}
        <img src="{{ route($photoRoute, $employee) }}" alt="" loading="lazy"
             class="absolute inset-0 h-full w-full rounded-full object-cover"
             onerror="this.remove()">
    @endif
</span>
