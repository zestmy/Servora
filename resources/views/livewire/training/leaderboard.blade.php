<div>
    <x-page-header title="Leaderboard" eyebrow="Learning & Development"
                   subtitle="Best score per quiz, per person — practising is safe, persistence is not a strategy." />

    <div class="toolbar mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="lb-period">Period</label>
                <select id="lb-period" wire:model.live="period" class="input">
                    @foreach (\App\Services\Training\LeaderboardService::PERIODS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="lb-outlet">Outlet</label>
                <select id="lb-outlet" wire:model.live="outletId" class="input">
                    <option value="">Whole company</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="lb-quiz">Quiz</label>
                <select id="lb-quiz" wire:model.live="quizId" class="input">
                    <option value="">Every quiz</option>
                    @foreach ($quizzes as $quiz)
                        <option value="{{ $quiz->id }}">{{ $quiz->title }}</option>
                    @endforeach
                </select>
            </div>
            <p class="help pb-2 max-w-prose">
                A fifteen-branch group has one person who is always top. Filter to a branch and the board
                becomes something a team can actually win.
            </p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="table-scroll">
            <table class="table-surface min-w-full">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-3 w-16">#</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Outlet</th>
                        <th class="px-4 py-3">Points</th>
                        <th class="px-4 py-3">Quizzes</th>
                        <th class="px-4 py-3">Accuracy</th>
                        <th class="px-4 py-3">Passed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($board as $row)
                        <tr wire:key="lb-{{ $row['employee_id'] }}" class="{{ $row['rank'] === 1 ? 'bg-brand-50/50' : '' }}">
                            <td class="px-4 py-3">
                                <span class="flex items-center gap-1.5">
                                    @if ($row['rank'] <= 3)
                                        <x-icon name="trophy" size="h-4 w-4"
                                                class="{{ $row['rank'] === 1 ? 'text-warning-600' : 'text-gray-500' }}" />
                                    @endif
                                    <span class="tabular-nums font-semibold text-gray-700">{{ $row['rank'] }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="flex items-center gap-2.5">
                                    <x-staff-avatar :name="$row['name']" size="h-8 w-8 text-xs"
                                                    :employee="auth()->user()->can('hr.view') ? ($row['employee_id'] ?? null) : null"
                                                    :photo="$row['photo'] ?? null"
                                                    photoRoute="hr.employees.photo" />
                                    <span class="font-medium text-gray-900">{{ $row['name'] }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $row['outlet'] ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums font-semibold text-gray-900">{{ number_format($row['score']) }}</td>
                            <td class="px-4 py-3 tabular-nums text-gray-700">{{ $row['quizzes'] }}</td>
                            <td class="px-4 py-3 tabular-nums text-gray-700">{{ $row['accuracy'] }}%</td>
                            <td class="px-4 py-3 tabular-nums text-gray-700">{{ $row['passed'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <x-icon name="trophy" size="h-8 w-8" class="text-gray-500" />
                                    <p class="empty-title">Nothing on the board yet</p>
                                    <p class="empty-body">
                                        Scores appear here once somebody finishes a quiz in this period.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Not started ──

         The board answers "who is winning". The management question is "who
         has not begun", and it cannot be read off a list of the people who
         have — chasing three names should not mean diffing a roster against a
         leaderboard.

         It carries no score, no rank and no history, only "not yet", and a
         person leaves it the moment they finish anything. Hidden entirely when
         the board is filtered to a single quiz: "has not taken ANY quiz" is a
         statement about a person, and the same list under a one-quiz filter
         would be a different claim under the same heading. --}}
    @if ($notStarted->isNotEmpty())
        <div class="card p-5 mt-4">
            <h2 class="text-sm font-semibold text-gray-900">
                Not started — {{ $notStarted->count() }} {{ Str::plural('person', $notStarted->count()) }}
            </h2>
            <p class="help mb-3">
                Active staff with nothing finished
                {{ Str::lower(\App\Services\Training\LeaderboardService::PERIODS[$period] ?? $period) }}.
                Assign them something, or run a live round at the next briefing.
            </p>

            <div class="flex flex-wrap gap-2">
                @foreach ($notStarted as $person)
                    <span wire:key="not-started-{{ $person->id }}"
                          class="flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 py-1 pl-1 pr-3">
                        <x-staff-avatar :name="$person->name" size="h-7 w-7 text-[10px]"
                                        :employee="auth()->user()->can('hr.view') ? $person->id : null"
                                        :photo="$person->photo_path"
                                        photoRoute="hr.employees.photo" />
                        <span class="text-sm text-gray-800">{{ $person->name }}</span>
                        @if ($person->outlet)
                            <span class="text-xs text-gray-500">{{ $person->outlet->name }}</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
