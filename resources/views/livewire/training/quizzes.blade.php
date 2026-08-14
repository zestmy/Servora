<div>
    <x-training.flash />

    <x-page-header title="Quizzes" eyebrow="Learning & Development"
                   subtitle="Every quiz, how big it is, and whether anyone has actually taken it.">
        <x-slot:actions>
            @can('training.manage')
                <a href="{{ route('training.courses.create') }}" class="btn-primary">+ New course &amp; quiz</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="toolbar mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="label" for="quiz-search">Search</label>
                <input id="quiz-search" type="search" wire:model.live.debounce.300ms="search"
                       class="input" placeholder="Quiz title">
            </div>
            <div>
                <label class="label" for="quiz-status">Status</label>
                <select id="quiz-status" wire:model.live="statusFilter" class="input">
                    <option value="">All</option>
                    @foreach (\App\Models\TrainingQuiz::STATUSES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="table-scroll">
            <table class="table-surface min-w-full">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-3">Quiz</th>
                        <th class="px-4 py-3">Questions</th>
                        <th class="px-4 py-3">Attempts</th>
                        <th class="px-4 py-3">Live rounds</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($quizzes as $quiz)
                        <tr wire:key="quiz-{{ $quiz->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $quiz->title }}</p>
                                <p class="text-xs text-gray-600">
                                    {{ $quiz->course?->title ?? 'No course attached' }}
                                    {{-- Only when it is NOT the default: a badge on every row is
                                         noise, and the thing worth spotting is the one that
                                         differs. An untagged quiz goes to everybody, which is
                                         the normal case and needs no marking. --}}
                                    @if ($quiz->section_id)
                                        <span class="badge-brand ml-1">{{ $quiz->sectionLabel() }}</span>
                                    @endif
                                    @if (($quiz->language ?? 'en') !== 'en')
                                        <span class="badge-neutral ml-1">{{ $quiz->languageLabel() }}</span>
                                    @endif
                                    @if ($quiz->generated_by_ai)
                                        <span class="badge-brand ml-1">AI drafted</span>
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                @if ($quiz->questions_count === 0)
                                    <span class="text-warning-700">0</span>
                                @else
                                    {{ $quiz->questions_count }}
                                @endif
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ $quiz->attempts_count }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ $quiz->sessions_count }}</td>
                            <td class="px-4 py-3">
                                <span class="{{ $quiz->status === 'published' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ \App\Models\TrainingQuiz::STATUSES[$quiz->status] ?? $quiz->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2 text-xs">
                                    @can('training.host')
                                        @if ($quiz->status === 'published' && $quiz->questions_count > 0)
                                            <a href="{{ route('training.live') }}" class="text-brand-600 hover:underline">Host</a>
                                        @endif
                                    @endcan
                                    @can('training.manage')
                                        <a href="{{ route('training.quizzes.edit', $quiz->id) }}"
                                           class="text-brand-600 hover:underline">Edit</a>
                                        @if ($quiz->status === 'published')
                                            <button wire:click="unpublish({{ $quiz->id }})"
                                                    class="text-gray-600 hover:text-gray-900">Unpublish</button>
                                        @else
                                            <button wire:click="publish({{ $quiz->id }})"
                                                    class="text-gray-600 hover:text-gray-900">Publish</button>
                                        @endif
                                        <button wire:click="delete({{ $quiz->id }})"
                                                data-confirm-delete="Delete &quot;{{ $quiz->title }}&quot;? Attempts and scores go with it."
                                                class="text-gray-600 hover:text-danger-500">Delete</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <x-icon name="clipboard" size="h-8 w-8" class="text-gray-500" />
                                    <p class="empty-title">No quizzes yet</p>
                                    <p class="empty-body">
                                        Quizzes are written from a course. Create the course, then press
                                        "Write the questions".
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $quizzes->links() }}</div>
</div>
