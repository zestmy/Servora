{{--
    What somebody sees a second after scanning the QR by the pass.

    One card, one button. Everything on it answers "is this for me and how long
    will it take" — the two questions a person standing up with their phone out
    actually has. The PIN comes after the button, on the app's own sign-in
    screen, because that is where the lockout and the email fallback live.
--}}
<div class="mx-auto max-w-md">

    @if (! $quiz)
        <div class="card p-8 text-center">
            <div class="mx-auto h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center">
                <x-icon name="academic" size="h-6 w-6" class="text-gray-500" />
            </div>
            <h1 class="text-xl font-bold text-gray-900 mt-4">This link has expired</h1>
            <p class="text-sm text-gray-600 mt-2">
                The quiz it pointed at is no longer published, or the link was mistyped.
                Ask your manager for a new one.
            </p>
            <a href="{{ route('clock.staff.login') }}" class="btn-secondary mt-5 w-full justify-center">
                Open the Staff Portal
            </a>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="bg-brand-600 px-5 py-4 text-white">
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-100">
                    {{ $company?->brand_name ?? $company?->name ?? 'Training' }}
                </p>
                <h1 class="mt-1 text-2xl font-bold leading-tight">{{ $quiz->title }}</h1>
                @if ($quiz->course)
                    <p class="mt-1 text-sm text-brand-100">{{ $quiz->course->title }}</p>
                @endif
            </div>

            <div class="p-5">
                @if ($quiz->description)
                    <p class="text-sm text-gray-700">{{ $quiz->description }}</p>
                @elseif ($quiz->course?->summary)
                    <p class="text-sm text-gray-700">{{ $quiz->course->summary }}</p>
                @endif

                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-surface bg-gray-50 px-2 py-3">
                        <p class="text-xl font-bold tabular-nums text-gray-900">{{ $quiz->questions_count }}</p>
                        <p class="text-xs text-gray-600">questions</p>
                    </div>
                    <div class="rounded-surface bg-gray-50 px-2 py-3">
                        <p class="text-xl font-bold tabular-nums text-gray-900">{{ $minutes }}</p>
                        <p class="text-xs text-gray-600">{{ Str::plural('minute', $minutes) }}</p>
                    </div>
                    <div class="rounded-surface bg-gray-50 px-2 py-3">
                        <p class="text-xl font-bold tabular-nums text-gray-900">{{ $quiz->pass_mark }}%</p>
                        <p class="text-xs text-gray-600">to pass</p>
                    </div>
                </div>

                <ul class="mt-4 space-y-1.5 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <x-icon name="clock" size="h-4 w-4" class="mt-0.5 shrink-0 text-brand-600" />
                        <span>{{ $quiz->default_seconds }} seconds a question — the faster you answer, the more it scores.</span>
                    </li>
                    @if ($quiz->wrong_penalty_percent > 0)
                        <li class="flex items-start gap-2">
                            <x-icon name="alert" size="h-4 w-4" class="mt-0.5 shrink-0 text-warning-600" />
                            <span>A wrong answer costs points on this one. Read before you tap.</span>
                        </li>
                    @endif
                    <li class="flex items-start gap-2">
                        <x-icon name="trophy" size="h-4 w-4" class="mt-0.5 shrink-0 text-warning-600" />
                        <span>Your score goes on the branch leaderboard.</span>
                    </li>
                    @if ($quiz->language !== 'en')
                        <li class="flex items-start gap-2">
                            <x-icon name="info" size="h-4 w-4" class="mt-0.5 shrink-0 text-info-600" />
                            <span>Dalam {{ $quiz->languageLabel() }}.</span>
                        </li>
                    @endif
                </ul>

                {{-- Full page load, not wire:navigate: this leaves the public
                     side of the app for a gated route, and the middleware that
                     stores the destination runs on a real request. --}}
                <a href="{{ route('clock.staff.learn.quiz', $quiz->id) }}"
                   class="btn-primary mt-5 w-full justify-center py-3.5 text-base">
                    Start — enter your PIN
                </a>

                <p class="help mt-3 text-center">
                    You will be asked for the same PIN you clock in with.
                </p>
            </div>
        </div>
    @endif
</div>
