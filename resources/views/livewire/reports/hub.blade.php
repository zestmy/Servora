<div>
    {{-- The reports hub, with a little kitchen personality: bars that rise
         like dough in the heading, a line of kitchen talk under each
         category, and a quip about numbers that changes once a day (picked
         by date, so it never flickers). Every link and the locked-report
         handling below are unchanged. Motion stops under
         prefers-reduced-motion. --}}
    <style>
        .rh-bar { transform-origin: bottom; transform-box: fill-box; animation: rh-rise 2.4s ease-in-out infinite; }
        .rh-bar:nth-of-type(2) { animation-delay: .25s; }
        .rh-bar:nth-of-type(3) { animation-delay: .5s; }
        @keyframes rh-rise { 0%, 100% { transform: scaleY(.55); } 50% { transform: scaleY(1); } }
        .rh-card { transition: transform .15s ease, box-shadow .15s ease; }
        .rh-card:hover { transform: translateY(-2px); }
        .rh-card:hover .rh-icon { animation: rh-wiggle .5s ease-in-out; }
        @keyframes rh-wiggle { 0%, 100% { transform: rotate(0); } 30% { transform: rotate(-10deg); } 70% { transform: rotate(8deg); } }
        @media (prefers-reduced-motion: reduce) {
            .rh-bar, .rh-card:hover .rh-icon { animation: none; }
            .rh-card, .rh-card:hover { transform: none; }
        }
    </style>

    @php
        // A line of kitchen talk under each category. Unknown titles get none.
        $flavour = [
            'Management'       => 'The big picture, plated for the owners.',
            'Purchase'         => 'What came in the back door, and what it cost.',
            'Order'            => 'Every order, from request to delivery.',
            'Inventory'        => 'What&rsquo;s on the shelves right now.',
            'Inventory Action' => 'Transfers, wastage and everything that moved.',
            'Menu'             => 'Which dishes earn their place on the menu.',
            'Kitchen'          => 'Prep, production and the line.',
            'HR'               => 'The brigade: hours, people and pay.',
            'Audits'           => 'How the kitchen scored on inspection.',
            'Others'           => 'The odds and ends drawer. Every kitchen has one.',
        ];

        $quips = [
            'Numbers don&rsquo;t lie. They just need a good chef to read them.',
            'A report a day keeps the month-end panic away.',
            'Food cost is a recipe too. Get the ingredients right.',
            'If it isn&rsquo;t measured, it&rsquo;s just a hunch with an apron on.',
            'The best kitchens taste their sauce and check their numbers.',
            'Waste is money in the bin. These reports help you fish it out.',
            'Margins are like rice: easy to burn if nobody&rsquo;s watching.',
        ];
        $quip = $quips[now()->dayOfYear % count($quips)];
    @endphp

    <div class="mb-6 flex items-center gap-4">
        <div class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-brand-50" aria-hidden="true">
            <svg class="h-7 w-7" viewBox="0 0 28 28" fill="none">
                <rect class="rh-bar" x="5"  y="12" width="4" height="11" rx="1.5" fill="#93c5fd" />
                <rect class="rh-bar" x="12" y="7"  width="4" height="16" rx="1.5" fill="#0962ef" />
                <rect class="rh-bar" x="19" y="10" width="4" height="13" rx="1.5" fill="#0748b3" />
            </svg>
        </div>
        <div class="min-w-0">
            <p class="page-eyebrow">The numbers pass</p>
            <h2 class="page-title mt-1">Reports</h2>
            <p class="page-subtitle">{!! $quip !!}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($categories as $cat)
            <div class="card rh-card p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="rh-icon w-10 h-10 flex-none bg-brand-50 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $cat['icon'] }}" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $cat['title'] }}</h3>
                        @isset($flavour[$cat['title']])
                            <p class="text-xs text-gray-500">{!! $flavour[$cat['title']] !!}</p>
                        @endisset
                    </div>
                </div>
                <div class="space-y-1.5">
                    @foreach ($cat['reports'] as $report)
                        @if (! empty($report['locked']))
                            {{-- Not on the plan: goes to Billing, as the sidebar's locked items do. --}}
                            <a href="{{ route('billing.index', ['unlock' => $report['locked']]) }}"
                               class="flex items-center gap-2 text-sm text-gray-600 hover:text-brand-600 hover:bg-brand-50 px-3 py-2 rounded-lg transition">
                                <span class="min-w-0 flex-1">{{ $report['label'] }}</span>
                                <x-icon name="lock" size="h-3.5 w-3.5" stroke="1.8" class="flex-shrink-0 text-gray-500" />
                                <span class="sr-only">(upgrade to unlock)</span>
                            </a>
                        @else
                            <a href="{{ route($report['route']) }}"
                               class="block text-sm text-gray-600 hover:text-brand-600 hover:bg-brand-50 px-3 py-2 rounded-lg transition">
                                {{ $report['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
