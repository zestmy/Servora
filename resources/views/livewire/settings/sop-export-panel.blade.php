{{--
    The full-catalogue SOP export.

    Four states in one card. The header row — icon, title, meta, action — keeps
    its structure in all of them so the state reads as the same object changing
    rather than the panel being replaced; the progress block below it is the
    only part that comes and goes, so the card does grow while a render runs.

    The progress bar has two modes because the work has two kinds of phase —
    see .progress in app.css. Everything measurable reports a real fraction;
    the dompdf render, which is one opaque call, reports as a sliding bar
    rather than a percentage that would sit still for a minute.
--}}
<div>
    @php
        $isRunning   = $running;
        $isDone      = $export?->isDownloadable();
        $isFailed    = $export && ($export->isFailed() || $export->isStale());
        $isRendering = $export?->phase === \App\Services\Pdf\SopExportBuilder::PHASE_RENDERING;
    @endphp

    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-3">
                {{-- The icon carries the state: brand while working, green
                     when there is a file, amber when it stopped. --}}
                <span @class([
                    'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full',
                    'bg-brand-50 text-brand-600'     => $isRunning || (! $isDone && ! $isFailed),
                    'bg-success-50 text-success-600' => $isDone,
                    'bg-warning-50 text-warning-600' => $isFailed,
                ])>
                    @if ($isRunning)
                        <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    @elseif ($isDone)
                        <x-icon name="check" />
                    @elseif ($isFailed)
                        <x-icon name="warning" />
                    @else
                        <x-icon name="document" />
                    @endif
                </span>

                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-gray-900">Full SOP Handbook</h3>

                    @if ($isRunning)
                        <p class="progress-meta mt-0.5">
                            {{ $export->status === \App\Models\SopExport::STATUS_QUEUED ? 'Queued' : 'Building' }}
                            @if ($export->started_at)
                                {{-- Ticks between polls so the card never looks frozen. --}}
                                <span x-data="{
                                        s: {{ max(0, now()->diffInSeconds($export->started_at, true)) }},
                                        t: null,
                                        init() { this.t = setInterval(() => this.s++, 1000) },
                                        destroy() { clearInterval(this.t) },
                                        get text() {
                                            const m = Math.floor(this.s / 60), r = this.s % 60
                                            return m ? `${m}m ${r}s` : `${r}s`
                                        },
                                      }" x-text="'· ' + text"></span>
                            @endif
                        </p>
                    @elseif ($isDone)
                        <p class="progress-meta mt-0.5">
                            {{ $export->humanSize() }}
                            @if ($export->recipe_count) · {{ $export->recipe_count }} recipes @endif
                            · built {{ $export->finished_at?->diffForHumans() }}
                        </p>
                    @elseif ($isFailed)
                        <p class="progress-meta mt-0.5 text-warning-700">
                            {{ $export->isStale() ? 'The export stopped before it finished.' : ($export->error ?: 'The export failed.') }}
                        </p>
                    @else
                        <p class="progress-meta mt-0.5">
                            All {{ $sopCount }} SOPs as one PDF — takes a couple of minutes, so it builds in the background.
                        </p>
                    @endif
                </div>
            </div>

            <div class="flex flex-shrink-0 items-center gap-2">
                @if ($isDone)
                    <a href="{{ route('training.sop.export.download', $export) }}"
                       class="btn-primary">
                        <x-icon name="download" size="h-4 w-4" />
                        Download PDF
                    </a>
                    <button type="button" wire:click="start" wire:loading.attr="disabled"
                            title="Build a fresh copy"
                            class="btn-secondary">
                        Rebuild
                    </button>
                @elseif ($isFailed)
                    <button type="button" wire:click="start" wire:loading.attr="disabled" class="btn-primary">
                        Try again
                    </button>
                @elseif (! $isRunning)
                    <button type="button" wire:click="start" wire:loading.attr="disabled" class="btn-primary">
                        <x-icon name="document" size="h-4 w-4" />
                        <span wire:loading.remove wire:target="start">Generate PDF</span>
                        <span wire:loading wire:target="start">Starting…</span>
                    </button>
                @endif
            </div>
        </div>

        @if ($isRunning)
            {{-- Only this subtree polls, and only while there is something to
                 poll for — see the component for why this is not on LmsUsers. --}}
            <div wire:poll.{{ $pollMs }}ms="refresh" class="mt-4">
                <div class="mb-1.5 flex items-baseline justify-between gap-3">
                    <span class="progress-label">{{ $export->progress_label ?: 'Working' }}</span>
                    {{-- No number during the render: there isn't an honest one. --}}
                    <span class="progress-meta">{{ $isRendering ? 'Almost there' : $export->progress . '%' }}</span>
                </div>

                @if ($isRendering)
                    <span class="progress progress-indeterminate"></span>
                @else
                    <span class="progress">
                        <span class="progress-fill" style="width: {{ max(2, (int) $export->progress) }}%"></span>
                    </span>
                @endif

                <p class="progress-meta mt-2">
                    @if ($typicalSeconds)
                        {{-- The render is most of the wait and cannot report from
                             inside dompdf, so this is the reference point instead
                             of a percentage that would be invented. --}}
                        Your last export took
                        {{ $typicalSeconds >= 60
                            ? intdiv($typicalSeconds, 60) . 'm ' . ($typicalSeconds % 60) . 's'
                            : $typicalSeconds . 's' }}.
                    @endif
                    You can leave this page — the file will be here when you come back.
                </p>
            </div>
        @endif
    </div>
</div>
