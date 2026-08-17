{{--
    The Staff Portal home screen.

    Ordered by what somebody standing in a doorway needs, not by what is most
    interesting: the clock first with its action, then what they owe, then how
    they are doing. The board comes last because it is the thing you look at
    when you have a minute, not the thing you open the app for.
--}}
<div class="space-y-3">

    {{-- ── Greeting ──
         Face and FULL name, before anything transactional. The same shared
         avatar as the account screen and the boards: photo over initials, so
         no photo on file or a 404 degrades to the coloured disc.

         The full name, not the first word. This shipped cutting at the first
         space and greeted MOHD AFFANDY BIN ZULKARNAIN as "MOHD" — the exact
         mistake the employee-avatar's initials note warns about: names here
         are not reliably given-name-first, and picking the "first name" out
         of one is guessing. The truncate handles long names; guessing does
         not. --}}
    @php
        $hour     = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    @endphp
    <div class="flex items-center gap-3 px-1">
        <x-staff-avatar :name="$employee->name" :employee="$employee->id"
                        :photo="$employee->photo_path" size="h-11 w-11 text-sm" />
        <div class="min-w-0">
            <p class="text-xs text-gray-600">{{ $greeting }}</p>
            <p class="truncate text-lg font-semibold leading-tight text-gray-900">
                {{ $employee->name }}
            </p>
        </div>
    </div>

    {{-- ── Clock ──
         The primary card and the primary action. Clocking in is one tap from
         the landing screen, which is the whole reason this page exists instead
         of a redirect to the board. --}}
    <div class="card overflow-hidden">
        <div class="{{ $open ? 'bg-success-600' : 'bg-gray-900' }} px-4 py-3 text-white">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-wider {{ $open ? 'text-success-100' : 'text-gray-400' }}">
                        {{ now()->format('l, j M') }}
                    </p>
                    <p class="mt-0.5 text-lg font-semibold leading-tight">
                        @if ($onBreak)
                            On break
                        @elseif ($open)
                            Clocked in
                        @else
                            Not clocked in
                        @endif
                    </p>
                </div>
                <x-icon name="clock" size="h-8 w-8" class="{{ $open ? 'text-success-200' : 'text-gray-500' }} shrink-0" />
            </div>

            @if ($open?->happened_at)
                <p class="mt-1 text-xs {{ $open ? 'text-success-100' : 'text-gray-300' }}">
                    Since {{ $open->happened_at->format('H:i') }}
                    · {{ $open->happened_at->diffForHumans(null, true) }} so far
                </p>
            @elseif ($last?->happened_at)
                <p class="mt-1 text-xs text-gray-300">
                    Last punch {{ $last->happened_at->format('H:i, j M') }}
                </p>
            @endif
        </div>

        <div class="p-3">
            <a href="{{ route('clock.staff.punch') }}" wire:navigate
               class="btn-primary w-full justify-center py-3 text-base">
                {{ $open ? 'Clock out' : 'Clock in' }}
            </a>
        </div>
    </div>

    {{-- ── What you owe ──
         Above the stats on purpose: an overdue course is the only thing on this
         screen that somebody else is waiting on. --}}
    @if ($outstanding->isNotEmpty())
        <div class="card border-l-4 border-l-warning-400 p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900">To do</h2>
                <span class="badge-warning">{{ $outstanding->count() }}</span>
            </div>
            <ul class="mt-2 space-y-2">
                @foreach ($outstanding->take(3) as $item)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-gray-900">{{ $item['title'] }}</span>
                        <span class="text-xs {{ $item['overdue'] ? 'font-semibold text-danger-700' : 'text-gray-600' }}">
                            @if ($item['due_on'])
                                {{ $item['overdue'] ? 'Overdue' : 'Due' }} {{ $item['due_on']->format('j M') }}
                            @else
                                No date
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('clock.staff.learn') }}" wire:navigate
               class="btn-secondary mt-3 w-full justify-center">
                Start learning
            </a>
        </div>
    @endif

    {{-- ── What you have earned ──

         Only when there is something. A certificate is looked for months after
         the quiz, by somebody who remembers passing and not where the result
         went — so it gets a doorway on the screen they open first, rather than
         living only under a heading about weak topics. --}}
    @if ($certificates > 0)
        <a href="{{ route('clock.staff.learn.certificates') }}" wire:navigate
           class="card flex items-center gap-3 p-4 active:bg-gray-50">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-success-100">
                <x-icon name="trophy" size="h-5 w-5" class="text-success-700" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-gray-900">My certificates</span>
                <span class="block text-xs text-gray-600">
                    {{ $certificates }} in date — tap to download
                </span>
            </span>
            <x-icon name="chevron-right" size="h-5 w-5" class="shrink-0 text-gray-400" />
        </a>
    @endif

    {{-- ── How you are doing this month ── --}}
    <div class="card p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-900">This month</h2>
            <a href="{{ route('clock.staff.learn.progress') }}" wire:navigate
               class="text-xs text-brand-600">Details</a>
        </div>

        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-surface bg-gray-50 py-3">
                <p class="text-xl font-bold tabular-nums text-gray-900">
                    {{ $position ? '#' . $position['rank'] : '—' }}
                </p>
                <p class="text-[11px] text-gray-600">
                    {{ $position ? 'of ' . $position['of'] : 'unranked' }}
                </p>
            </div>
            <div class="rounded-surface bg-gray-50 py-3">
                <p class="text-xl font-bold tabular-nums text-gray-900">{{ number_format($points) }}</p>
                <p class="text-[11px] text-gray-600">points</p>
            </div>
            <div class="rounded-surface bg-gray-50 py-3">
                <p class="text-xl font-bold tabular-nums text-gray-900">
                    {{ $accuracy === null ? '—' : $accuracy . '%' }}
                </p>
                <p class="text-[11px] text-gray-600">accuracy</p>
            </div>
        </div>

        @if ($points === 0 && ! $position)
            <p class="help mt-3">
                Nothing yet this month. Anything you pass puts you on the board.
            </p>
        @endif
    </div>

    {{-- ── Top of the board ── --}}
    @if ($top->isNotEmpty())
        <div class="card p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900">
                    Top of {{ $employee->outlet?->name ?? 'the company' }}
                </h2>
                <a href="{{ route('clock.staff.leaderboard') }}" wire:navigate
                   class="text-xs text-brand-600">Full board</a>
            </div>

            <ol class="mt-2 divide-y divide-gray-100">
                @foreach ($top as $row)
                    @php $isMe = $row['employee_id'] === $employee->id; @endphp
                    <li wire:key="top-{{ $row['employee_id'] }}"
                        class="flex items-center gap-3 py-2 text-sm {{ $isMe ? 'font-semibold text-brand-900' : 'text-gray-800' }}">
                        <span class="w-5 shrink-0 text-right">
                            @if ($row['rank'] === 1)
                                <x-icon name="trophy" size="h-4 w-4" class="text-warning-500" />
                            @else
                                <span class="tabular-nums text-gray-500">{{ $row['rank'] }}</span>
                            @endif
                        </span>
                        <x-staff-avatar :name="$row['name']" :employee="$row['employee_id'] ?? null" :photo="$row['photo'] ?? null" size="h-7 w-7 text-[10px]" />
                        <span class="min-w-0 flex-1 truncate">
                            {{ $row['name'] }}
                            @if ($isMe)
                                <span class="ml-1 text-[11px] uppercase tracking-wide text-brand-700">you</span>
                            @endif
                        </span>
                        <span class="tabular-nums">{{ number_format($row['score']) }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- ── Everything else ──
         A grid rather than more tabs: the bar is already carrying more than it
         should, and these are things somebody goes looking for rather than
         switches between. --}}
    <div class="grid grid-cols-2 gap-2">
        @foreach ([
            ['route' => 'clock.staff.learn',     'label' => 'Training',  'icon' => 'academic'],
            ['route' => 'clock.staff.live',      'label' => 'Live quiz', 'icon' => 'play'],
            ['route' => 'clock.staff.history',   'label' => 'My punches','icon' => 'clipboard'],
            ['route' => 'clock.staff.roster',    'label' => 'Roster',    'icon' => 'calendar-days'],
            ['route' => 'clock.staff.leave',     'label' => 'Leave',     'icon' => 'document'],
            ['route' => 'clock.staff.time-off',  'label' => 'Time off',  'icon' => 'bolt'],
            ['route' => 'clock.staff.payslips',  'label' => 'Payslips',  'icon' => 'currency'],
        ] as $link)
            <a href="{{ route($link['route']) }}" wire:navigate
               class="card flex min-h-[3.5rem] items-center gap-3 px-4 py-3 active:bg-gray-50">
                <x-icon :name="$link['icon']" size="h-5 w-5" class="shrink-0 text-brand-600" />
                <span class="text-sm font-medium text-gray-900">{{ $link['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
