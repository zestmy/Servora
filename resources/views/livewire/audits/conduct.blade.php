<div>
    @if (session()->has('success'))
        <div wire:key="flash-{{ microtime(true) }}" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
             class="alert-success mb-4">{{ session('success') }}</div>
    @endif
    @error('audit') <div class="alert-danger mb-4">{{ $message }}</div> @enderror

    @php
        $pct  = $audit->score_percent !== null ? (float) $audit->score_percent : null;
        $band = \App\Services\Audits\AuditScoreService::band($pct);
    @endphp

    <x-page-header :title="$audit->outlet?->name ?? 'Audit'"
                   :eyebrow="($audit->template_code ? $audit->template_code . ' · ' : '') . $audit->template_name"
                   :subtitle="$audit->audit_date->format('l, j M Y') . ' · ' . ($audit->auditor?->name ?? '')">
        <x-slot:actions>
            <a href="{{ route('audits.index') }}" wire:navigate class="btn-secondary">
                <x-icon name="chevron-left" size="h-4 w-4" /><span class="hidden sm:inline">Audits</span>
            </a>
            @unless ($isDraft)
                <a href="{{ route('audits.report', $audit->id) }}" target="_blank" class="btn-secondary">
                    <x-icon name="printer" size="h-4 w-4" /><span class="hidden sm:inline">PDF report</span>
                </a>
                @if ($canReopen)
                    <button wire:click="reopen" wire:confirm="Reopen this audit? It goes back to draft and the outlet's acknowledgement is cleared."
                            class="btn-secondary">Reopen</button>
                @endif
            @endunless
            <span @class([
                'badge-neutral' => $audit->status === 'draft',
                'badge-info'    => $audit->status === 'submitted',
                'badge-warning' => $audit->status === 'acknowledged',
                'badge-success' => $audit->status === 'closed',
            ])>{{ $audit->statusLabel() }}</span>
        </x-slot:actions>
    </x-page-header>

    {{-- Score strip: sticks under the header so the running score is
         readable from the fortieth row. Sections scroll sideways on a phone. --}}
    <div class="sticky top-14 z-10 -mx-1 mb-3 bg-gray-50/95 px-1 py-2 backdrop-blur supports-[backdrop-filter]:bg-gray-50/80 md:top-0">
        <div class="card px-3 py-2">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 pr-3 border-r border-gray-200">
                    <p class="stat-label">Score</p>
                    <p class="text-lg font-semibold tabular-nums leading-tight {{ $bands[$band] }}">
                        {{ $pct === null ? '—' : number_format($pct, 1) . '%' }}
                    </p>
                </div>
                <div class="min-w-0 flex-1 overflow-x-auto">
                    <div class="flex items-center gap-1">
                        @foreach ($audit->sections as $s)
                            @php $sPct = $s->score_percent !== null ? (float) $s->score_percent : null; @endphp
                            <button type="button" wire:click="selectSection({{ $s->id }})"
                                    class="flex h-11 flex-shrink-0 flex-col justify-center rounded-control px-3 text-left
                                           {{ $view === 'sections' && $sectionId === $s->id ? 'bg-brand-50 text-brand-800' : 'text-gray-700 hover:bg-gray-100' }}">
                                <span class="whitespace-nowrap text-xs font-medium leading-tight">{{ $s->name }}</span>
                                <span class="whitespace-nowrap text-[11px] tabular-nums leading-tight {{ $s->isComplete() ? 'text-gray-600' : 'text-warning-700' }}">
                                    {{ $s->answered_count }}/{{ $s->leaf_count }}{{ $s->isPenalty() ? ' · −' . $s->lost_points : ($sPct !== null ? ' · ' . number_format($sPct, 0) . '%' : '') }}
                                </span>
                            </button>
                        @endforeach
                        @unless ($isDraft)
                            <button type="button" wire:click="showSummary"
                                    class="flex h-11 flex-shrink-0 items-center rounded-control px-3 text-xs font-medium
                                           {{ $view === 'summary' ? 'bg-brand-50 text-brand-800' : 'text-gray-700 hover:bg-gray-100' }}">
                                Summary
                            </button>
                        @endunless
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($view === 'summary')
        @include('livewire.audits.partials.summary')
    @else
        {{-- Header card, collapsed by default once anything is answered --}}
        <div class="card mb-3 p-4" x-data="{ open: {{ $audit->sections->sum('answered_count') === 0 ? 'true' : 'false' }} }">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-3 text-left">
                <span class="text-sm font-semibold text-gray-900">Audit details</span>
                <span class="flex items-center gap-2 text-xs text-gray-600">
                    {{ $timeIn ?: '—' }} → {{ $timeOut ?: '—' }}
                    <x-icon name="chevron-down" size="h-5 w-5" class="text-gray-500 transition" x-bind:class="open ? 'rotate-180' : ''" />
                </span>
            </button>
            <div x-show="open" x-collapse class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="label">Time in</label>
                    <input type="time" wire:model.blur="timeIn" class="input" @disabled(! $isDraft || ! $canConduct) />
                </div>
                <div>
                    <label class="label">Time out</label>
                    <input type="time" wire:model.blur="timeOut" class="input" @disabled(! $isDraft || ! $canConduct) />
                </div>
                <div>
                    <label class="label">Reference</label>
                    <input type="text" wire:model.blur="reference" class="input" @disabled(! $isDraft || ! $canConduct) />
                </div>
                @foreach ($audit->header_values ?? [] as $field)
                    <div wire:key="hf-{{ $field['key'] }}">
                        <label class="label">{{ $field['label'] }}</label>
                        @if ($field['type'] === 'employee')
                            <select wire:model.live="headerValues.{{ $field['key'] }}" class="input" @disabled(! $isDraft || ! $canConduct)>
                                <option value="">—</option>
                                @foreach ($employees as $e)
                                    <option value="{{ $e->name }}">{{ $e->name }}{{ $e->designation ? ' · ' . $e->designation : '' }}</option>
                                @endforeach
                            </select>
                        @elseif ($field['type'] === 'textarea')
                            <textarea wire:model.blur="headerValues.{{ $field['key'] }}" rows="2" class="input" @disabled(! $isDraft || ! $canConduct)></textarea>
                        @else
                            <input type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'time' ? 'time' : 'text') }}"
                                   wire:model.blur="headerValues.{{ $field['key'] }}" class="input" @disabled(! $isDraft || ! $canConduct) />
                        @endif
                    </div>
                @endforeach
                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="label">Notes</label>
                    <textarea wire:model.blur="notes" rows="2" class="input" @disabled(! $isDraft || ! $canConduct)></textarea>
                </div>
            </div>
        </div>

        @if ($section)
            <div class="card overflow-hidden" wire:key="section-{{ $section->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-900">{{ $section->name }}</h2>
                        @if ($section->name_alt)
                            <p class="text-xs text-gray-600">{{ $section->name_alt }}</p>
                        @endif
                    </div>
                    <p class="text-xs tabular-nums text-gray-600">
                        @if ($section->isPenalty())
                            Penalty section · {{ $section->lost_points }} pts deducted from the total
                        @else
                            {{ $section->score_points }} / {{ $section->available_points }} pts
                            @if ($section->na_points) · {{ $section->na_points }} N/A @endif
                        @endif
                    </p>
                </div>

                <ul class="divide-y divide-gray-100">
                    @foreach ($lines as $line)
                        @include('livewire.audits.partials.line', ['line' => $line, 'photos' => $photosByLine->get($line->id, collect())])
                    @endforeach
                </ul>

                @if ($isDraft && $canConduct)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-4 py-3">
                        @php $remaining = max(0, $section->leaf_count - $section->answered_count); @endphp
                        <button type="button" wire:click="markRemainingOk" class="btn-secondary" @disabled($remaining === 0)>
                            <x-icon name="check" size="h-4 w-4" />
                            Mark the rest OK{{ $remaining ? " ({$remaining})" : '' }}
                        </button>
                        @if ($isLastSection)
                            <button type="button" wire:click="submit" wire:confirm="Submit this audit? The score becomes final and every NC becomes a finding for the outlet to act on."
                                    class="btn-primary">
                                Submit audit{{ $unanswered ? " · {$unanswered} unanswered" : '' }}
                            </button>
                        @else
                            <button type="button" wire:click="nextSection" class="btn-primary">
                                Next section <x-icon name="arrow-right" size="h-4 w-4" />
                            </button>
                        @endif
                    </div>
                @elseif ($isDraft && ! $canConduct)
                    <p class="help px-4 py-3">You can read this audit but not conduct it.</p>
                @endif
            </div>
        @endif
    @endif
</div>
