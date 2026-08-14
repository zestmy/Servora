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
</div>
