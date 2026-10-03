{{-- A small bit of kitchen personality under the dashboard heading: an icon
     and a line for where the day is in service, plus a chef's tip.

     Everything here is chosen by the clock and the date, never at random, so
     the strip does not change each time a filter re-renders the dashboard;
     the tip changes once a day. "Hide for today" is remembered in this
     browser only (localStorage, wrapped in try/catch) and the block is
     wire:ignore'd so a Livewire re-render does not bring it back. Motion
     stops under prefers-reduced-motion. --}}
@php
    $hour = (int) now()->format('G');

    $moment = match (true) {
        $hour < 5  => ['icon' => 'moon',   'title' => 'Burning the midnight oil', 'line' => 'The kitchen&rsquo;s closed, but the numbers never sleep.'],
        $hour < 11 => ['icon' => 'coffee', 'title' => 'Prep time',                'line' => 'Knives sharp, stock counted, coffee hot. The line is yours.'],
        $hour < 15 => ['icon' => 'wok',    'title' => 'Lunch rush',               'line' => 'Tickets are flying. Keep them moving.'],
        $hour < 17 => ['icon' => 'coffee', 'title' => 'Between services',         'line' => 'Breathe, restock, check the numbers below.'],
        $hour < 22 => ['icon' => 'wok',    'title' => 'Dinner service',           'line' => 'Fire away, chef. The pass is busy.'],
        default    => ['icon' => 'moon',   'title' => 'Closing time',             'line' => 'Wipe down, count up, lights off. Good shift.'],
    };

    $tips = [
        'First in, first out. The walk-in is not a time capsule.',
        'Count stock on the same day each week. A trend tells you more than a single count.',
        'A 1% drop in food cost on RM50,000 of monthly sales is RM500 back in your pocket.',
        'Weigh your portions for a week. The spoon is often more generous than the recipe.',
        'Wastage you record is wastage you can fix. Wastage you bin quietly just costs money.',
        'When a supplier raises a price, check which recipes use it before the next order.',
        'Label everything with a date. Future you will thank present you.',
        'The best-selling dish is not always the most profitable one. Check both.',
        'A clean station is a fast station.',
        'Train one new skill a week and the team covers for each other on busy nights.',
        'Check deliveries against the PO at the door, not after the driver has gone.',
        'Menu prices drift behind costs quietly. Review them every quarter.',
        'Mise en place for the office too: a quick look here before service saves surprises.',
        'Happy staff, happy guests. Say thank you after a hard shift.',
    ];
    $tip = $tips[now()->dayOfYear % count($tips)];
    $todayKey = 'servora.kitchenMoment.hidden.' . now()->toDateString();
@endphp

<div wire:ignore
     x-data="{
         hidden: (() => { try { return localStorage.getItem(@js($todayKey)) === '1' } catch (e) { return false } })(),
         hide() { this.hidden = true; try { localStorage.setItem(@js($todayKey), '1') } catch (e) {} }
     }"
     x-show="! hidden"
     class="km mb-6 flex items-start gap-4 rounded-surface border border-brand-100 bg-gradient-to-r from-brand-50 to-white px-4 py-3.5 sm:items-center">
    <style>
        .km-steam path { stroke-dasharray: 14; animation: km-steam 2.4s ease-in-out infinite; }
        .km-steam path:nth-child(2) { animation-delay: .4s; }
        .km-steam path:nth-child(3) { animation-delay: .8s; }
        @keyframes km-steam { 0% { stroke-dashoffset: 14; opacity: 0; } 40% { opacity: 1; } 100% { stroke-dashoffset: -14; opacity: 0; } }
        .km-toss { transform-origin: 50% 80%; animation: km-toss 2.2s ease-in-out infinite; }
        @keyframes km-toss { 0%, 60%, 100% { transform: rotate(0); } 70% { transform: rotate(-10deg) translateY(-2px); } 82% { transform: rotate(5deg); } }
        .km-twinkle { animation: km-twinkle 2.6s ease-in-out infinite; transform-box: fill-box; transform-origin: center; }
        @keyframes km-twinkle { 0%, 100% { opacity: .25; transform: scale(.7); } 50% { opacity: 1; transform: scale(1); } }
        @media (prefers-reduced-motion: reduce) {
            .km-steam path, .km-toss, .km-twinkle { animation: none; }
        }
    </style>

    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-white shadow-e1" aria-hidden="true">
        @if ($moment['icon'] === 'coffee')
            <svg class="h-7 w-7" viewBox="0 0 32 32" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <g class="km-steam" stroke="#9ca3af" stroke-width="1.6">
                    <path d="M11 9 q-2 -3 0 -6" /><path d="M15 9 q-2 -3 0 -6" /><path d="M19 9 q-2 -3 0 -6" />
                </g>
                <path d="M7 12 h16 v8 a6 6 0 0 1 -6 6 h-4 a6 6 0 0 1 -6 -6 z" fill="#0962ef" />
                <path d="M23 14 h2 a3 3 0 0 1 0 6 h-2" stroke="#0962ef" stroke-width="2" />
            </svg>
        @elseif ($moment['icon'] === 'wok')
            <svg class="h-7 w-7" viewBox="0 0 32 32" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <g class="km-toss">
                    <circle cx="12" cy="10" r="1.6" fill="#f59e0b" />
                    <circle cx="16" cy="7" r="1.4" fill="#10b981" />
                    <circle cx="19" cy="11" r="1.5" fill="#ef4444" />
                    <path d="M4 16 h20 a10 7 0 0 1 -20 0 z" fill="#0962ef" />
                    <path d="M24 17 l5 -2" stroke="#374151" stroke-width="2.4" />
                </g>
            </svg>
        @else
            <svg class="h-7 w-7" viewBox="0 0 32 32" fill="none">
                <path d="M20 5 a11 11 0 1 0 7 19 a9 9 0 0 1 -7 -19 z" fill="#0962ef" />
                <path class="km-twinkle" d="M25 6 l1 -2.5 l1 2.5 l2.5 1 l-2.5 1 l-1 2.5 l-1 -2.5 l-2.5 -1 z" fill="#f59e0b" />
            </svg>
        @endif
    </div>

    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-gray-900">{!! $moment['title'] !!}
            <span class="font-normal text-gray-600">&middot; {!! $moment['line'] !!}</span>
        </p>
        <p class="mt-0.5 text-xs text-gray-600">
            <span class="font-medium text-brand-700">Chef&rsquo;s tip:</span> {{ $tip }}
        </p>
    </div>

    <button type="button" @click="hide()"
            class="btn-ghost btn-icon -mr-1 flex-none text-gray-500"
            aria-label="Hide for today" title="Hide for today">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
        </svg>
    </button>
</div>
