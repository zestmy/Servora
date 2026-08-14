{{--
    The player's phone.

    Polls every second, and everything on screen is derived from the session row
    on each poll — so a phone that missed three polls catches up rather than
    resuming where it left off. See App\Models\TrainingSession.
--}}
<div class="mx-auto max-w-md" wire:poll.1s="heartbeat">

    <div class="mb-4 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate font-semibold text-gray-900">{{ $player->nickname }}</p>
            <p class="text-xs text-gray-600">
                {{ number_format($player->score) }} points
                @if ($total > 1) · {{ $rank }} of {{ $total }} @endif
                @if ($player->streak >= 3)
                    · <span class="text-warning-700 font-medium">{{ $player->streak }} in a row</span>
                @endif
            </p>
        </div>
        <button type="button" wire:click="leave" class="text-xs text-gray-600 hover:text-gray-900">Leave</button>
    </div>

    @if ($session->status === 'lobby')
        <div class="card p-8 text-center">
            <div class="mx-auto h-12 w-12 rounded-full bg-brand-100 flex items-center justify-center">
                <x-icon name="clock" size="h-6 w-6" class="text-brand-700" />
            </div>
            <h1 class="text-xl font-bold text-gray-900 mt-4">You're in</h1>
            <p class="text-sm text-gray-600 mt-1">
                Waiting for the host to start. Keep this screen open.
            </p>
        </div>

    @elseif ($session->status === 'ended')
        <div class="card p-8 text-center">
            @if ($rank === 1)
                <x-icon name="trophy" size="h-12 w-12" class="mx-auto text-warning-500" />
                <h1 class="text-2xl font-bold text-gray-900 mt-3">You won</h1>
            @else
                <x-icon name="academic" size="h-12 w-12" class="mx-auto text-gray-500" />
                <h1 class="text-2xl font-bold text-gray-900 mt-3">Finished {{ $rank }}{{ [1 => 'st', 2 => 'nd', 3 => 'rd'][$rank] ?? 'th' }}</h1>
            @endif

            <p class="text-3xl font-bold tabular-nums text-brand-700 mt-4">{{ number_format($player->score) }}</p>
            <p class="text-sm text-gray-600">
                {{ $player->correct_count }} of {{ $session->questionCount() }} right
                @if ($player->best_streak >= 3) · best streak {{ $player->best_streak }} @endif
            </p>
        </div>

        <div class="card p-5 mt-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Top of the room</h2>
            <ol class="space-y-1">
                @foreach ($podium as $i => $row)
                    <li wire:key="podium-{{ $row->id }}"
                        class="flex items-center justify-between gap-3 py-1 text-sm
                               {{ $row->id === $player->id ? 'font-semibold text-brand-800' : 'text-gray-800' }}">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="w-4 text-right tabular-nums text-gray-600">{{ $i + 1 }}</span>
                            <span class="truncate">{{ $row->nickname }}</span>
                        </span>
                        <span class="tabular-nums">{{ number_format($row->score) }}</span>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="mt-4 flex gap-2">
            <a href="{{ route('clock.staff.learn.progress') }}" class="btn-primary flex-1 text-center">My progress</a>
            <a href="{{ route('clock.staff.learn') }}" class="btn-ghost flex-1 text-center">Training</a>
        </div>

    @elseif ($session->status === 'reveal')
        <div class="card p-8 text-center">
            <p class="text-sm text-gray-600">Question {{ $session->current_index + 1 }} of {{ $session->questionCount() }}</p>
            <h1 class="text-xl font-bold text-gray-900 mt-2">Answer's on the big screen</h1>
            <p class="text-3xl font-bold tabular-nums text-brand-700 mt-4">{{ number_format($player->score) }}</p>
            <p class="text-sm text-gray-600">points so far</p>
        </div>

    @elseif ($question)
        <div class="card p-5 mb-3">
            <div class="flex items-center justify-between gap-3 mb-2">
                <p class="text-xs font-medium text-gray-600">
                    Question {{ $session->current_index + 1 }} of {{ $session->questionCount() }}
                </p>
                <span class="badge-brand tabular-nums">{{ $remaining }}s</span>
            </div>
            <p class="text-lg font-semibold leading-snug text-gray-900">{{ $question->prompt }}</p>
            @if ($question->isMultiSelect() && ! $alreadyAnswered)
                <p class="help mt-2">Choose all that apply, then press Send.</p>
            @endif
        </div>

        @if ($alreadyAnswered)
            <div class="card p-8 text-center">
                <x-icon name="check" size="h-10 w-10" class="mx-auto text-success-600" />
                <p class="mt-3 font-semibold text-gray-900">Answer in</p>
                <p class="text-sm text-gray-600 mt-1">Look up — the room is waiting for everyone else.</p>
            </div>
        @else
            {{-- 44px minimum, gloved hands. Not optional in the staff apps. --}}
            <div class="space-y-2">
                @foreach ($question->optionList() as $index => $option)
                    @php $isChosen = in_array($index, array_map('intval', $chosen), true); @endphp
                    <button type="button" wire:key="live-opt-{{ $question->id }}-{{ $index }}"
                            wire:click="pick({{ $index }}, {{ $question->isMultiSelect() ? 'true' : 'false' }})"
                            class="flex w-full items-center gap-3 rounded-surface border px-4 py-4 text-left text-base
                                   min-h-[3.25rem] transition
                                   {{ $isChosen
                                      ? 'border-brand-500 bg-brand-50 text-brand-900'
                                      : 'border-gray-200 bg-white text-gray-900 hover:border-brand-300' }}">
                        <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border
                                     {{ $isChosen ? 'border-brand-500' : 'border-gray-300' }}">
                            @if ($isChosen)
                                <span class="h-2.5 w-2.5 rounded-full bg-brand-600" aria-hidden="true"></span>
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">{{ $option }}</span>
                    </button>
                @endforeach
            </div>

            @if ($question->isMultiSelect())
                <button type="button" wire:click="send" class="btn-primary w-full mt-3 py-3"
                        @disabled(empty($chosen))>
                    Send
                </button>
            @endif
        @endif
    @else
        <div class="card p-8 text-center">
            <p class="text-sm text-gray-600">Waiting for the next question…</p>
        </div>
    @endif
</div>
