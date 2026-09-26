{{-- The record of a submitted audit: score card, findings, sign-off. --}}
@php
    $areas = $audit->sections->filter(fn ($s) => ! $s->isPenalty());
    $penalties = $audit->sections->filter(fn ($s) => $s->isPenalty());
@endphp

{{-- The verdict, before the numbers: it is the one thing the outlet manager
     reads, and the reasons say which rule decided it. --}}
@if ($audit->outcome)
    @php $rules = $audit->outcomeRules(); @endphp
    <div @class([
        'mb-3 rounded-surface border p-4',
        'border-success-200 bg-success-50' => $audit->outcome === 'pass',
        'border-warning-200 bg-warning-50' => $audit->outcome === 'conditional',
        'border-danger-200 bg-danger-50'   => $audit->outcome === 'fail',
    ])>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider {{ $audit->outcome === 'pass' ? 'text-success-800' : ($audit->outcome === 'conditional' ? 'text-warning-800' : 'text-danger-800') }}">Outcome</p>
                <p class="text-2xl font-semibold {{ $audit->outcome === 'pass' ? 'text-success-800' : ($audit->outcome === 'conditional' ? 'text-warning-800' : 'text-danger-800') }}">{{ $audit->outcomeLabel() }}</p>
            </div>
            <p class="text-xs {{ $audit->outcome === 'pass' ? 'text-success-800' : ($audit->outcome === 'conditional' ? 'text-warning-800' : 'text-danger-800') }}">
                Pass: ≥{{ $rules['pass_percent'] }}%, ≤{{ $rules['max_major_pass'] }} major, every area ≥{{ $rules['section_pass_percent'] }}% ·
                Conditional: ≥{{ $rules['conditional_percent'] }}%, ≤{{ $rules['max_major_conditional'] }} major, every area ≥{{ $rules['section_conditional_percent'] }}%
            </p>
        </div>
        @if ($audit->outcome_reasons)
            <ul class="mt-2 list-disc space-y-0.5 pl-5 text-sm {{ $audit->outcome === 'conditional' ? 'text-warning-800' : 'text-danger-800' }}">
                @foreach ($audit->outcome_reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        @endif
        @if ($audit->outcome === 'conditional' && $audit->reaudit_due_on)
            @php $followUp = $audit->completedReaudit(); @endphp
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-warning-200 pt-3">
                @if ($followUp)
                    <p class="text-sm text-warning-800">
                        Re-audited on
                        <a href="{{ route('audits.show', $followUp->id) }}" wire:navigate class="font-medium underline">{{ $followUp->audit_date->format('d M Y') }}</a>
                        — {{ $followUp->outcomeLabel() ?? $followUp->statusLabel() }}{{ $followUp->score_percent !== null ? ' · ' . number_format($followUp->score_percent, 1) . '%' : '' }}.
                    </p>
                @else
                    <p class="text-sm {{ $audit->isReauditOverdue() ? 'font-semibold text-danger-700' : 'text-warning-800' }}">
                        Re-audit due by {{ $audit->reaudit_due_on->format('d M Y') }}
                        ({{ $audit->reaudit_due_on->isPast() && ! $audit->reaudit_due_on->isToday() ? 'overdue, was ' : '' }}{{ $audit->reaudit_due_on->diffForHumans() }}).
                    </p>
                    @if ($canConduct && $audit->audit_template_id)
                        <a href="{{ route('audits.start', ['reaudit' => $audit->id]) }}" wire:navigate class="btn-primary text-xs">Start re-audit</a>
                    @endif
                @endif
            </div>
        @elseif ($audit->outcome === 'conditional')
            <p class="mt-2 text-sm text-warning-800">Re-audit within {{ $rules['reaudit_days'] }} days.</p>
        @endif
        @if ($audit->reaudit_of_id && $audit->reauditOf)
            <p class="mt-2 text-xs {{ $audit->outcome === 'pass' ? 'text-success-800' : ($audit->outcome === 'conditional' ? 'text-warning-800' : 'text-danger-800') }}">
                Re-audit of the conditional pass on
                <a href="{{ route('audits.show', $audit->reaudit_of_id) }}" wire:navigate class="font-medium underline">{{ $audit->reauditOf->audit_date->format('d M Y') }}</a>.
            </p>
        @endif
    </div>
@endif

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="card p-4 sm:col-span-2 lg:col-span-1">
        <p class="stat-label">Total score</p>
        <p class="stat-value {{ $bands[$band] }}">{{ $pct === null ? '—' : number_format($pct, 2) . '%' }}</p>
        <p class="stat-meta tabular-nums">
            {{ $audit->score_points }} of {{ $audit->available_points }} pts
            @if ($audit->penalty_points) · <span class="text-danger-700">−{{ $audit->penalty_points }} penalty</span> @endif
        </p>
        <p class="stat-meta">{{ $audit->finding_count }} finding{{ $audit->finding_count === 1 ? '' : 's' }}</p>
    </div>
    @foreach ($audit->sections as $s)
        @php $sPct = $s->score_percent !== null ? (float) $s->score_percent : null; $sBand = \App\Services\Audits\AuditScoreService::band($sPct); @endphp
        <div class="card p-4">
            <p class="stat-label truncate">{{ $s->name }}</p>
            @if ($s->isPenalty())
                <p class="stat-value {{ $s->lost_points ? 'text-danger-700' : 'text-success-700' }}">−{{ $s->lost_points }}</p>
                <p class="stat-meta">penalty · {{ $s->leaf_count }} critical items</p>
            @else
                <p class="stat-value {{ $bands[$sBand] }}">{{ $sPct === null ? '—' : number_format($sPct, 1) . '%' }}</p>
                <p class="stat-meta tabular-nums">{{ $s->score_points }} / {{ $s->available_points }} · lost {{ $s->lost_points }}@if ($s->na_points) · N/A {{ $s->na_points }}@endif</p>
            @endif
        </div>
    @endforeach
</div>

{{-- Header facts --}}
<div class="card mt-3 p-4">
    <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="stat-label">Auditor</dt><dd class="text-gray-900">{{ $audit->auditor?->name ?? '—' }}</dd></div>
        <div><dt class="stat-label">Time</dt><dd class="text-gray-900 tabular-nums">{{ $timeIn ?: '—' }} → {{ $timeOut ?: '—' }}</dd></div>
        <div><dt class="stat-label">Reference</dt><dd class="text-gray-900">{{ $audit->reference_number ?: '—' }}</dd></div>
        <div><dt class="stat-label">Submitted</dt><dd class="text-gray-900">{{ $audit->submitted_at?->format('d M Y H:i') ?? '—' }}</dd></div>
        @foreach ($audit->header_values ?? [] as $field)
            <div><dt class="stat-label">{{ $field['label'] }}</dt><dd class="text-gray-900">{{ $field['value'] ?: '—' }}</dd></div>
        @endforeach
        @if ($audit->notes)
            <div class="sm:col-span-2 lg:col-span-4"><dt class="stat-label">Notes</dt><dd class="whitespace-pre-line text-gray-900">{{ $audit->notes }}</dd></div>
        @endif
    </dl>
</div>

{{-- Findings and corrective actions --}}
<div class="mt-4">
    <div class="mb-2 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900">Non-conformances &amp; corrective actions</h2>
        <button type="button" wire:click="selectSection({{ $audit->sections->first()?->id }})" class="btn-ghost text-xs">Browse every item</button>
    </div>

    @if ($findings->isEmpty())
        <div class="card p-8">
            <div class="empty-state">
                <x-icon name="check" size="h-8 w-8" class="text-success-600" />
                <p class="empty-title">No non-conformances</p>
                <p class="empty-body">Every scored item passed or was not applicable.</p>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($findings as $sectionName => $group)
                <div class="card overflow-hidden" wire:key="fsec-{{ md5($sectionName) }}">
                    <div class="border-b border-gray-100 bg-gray-50 px-4 py-2.5">
                        <h3 class="text-sm font-semibold text-gray-800">{{ $sectionName }} <span class="ml-1 font-normal text-gray-600">· {{ $group->count() }}</span></h3>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($group as $finding)
                            <li wire:key="finding-{{ $finding->id }}" class="p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900">{{ $finding->item_label }}</p>
                                        @if ($finding->description)
                                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $finding->description }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-shrink-0 items-center gap-1.5">
                                        @if ($finding->isMajor())<span class="badge-danger">Major</span>@endif
                                        <span class="badge-neutral tabular-nums">−{{ $finding->points_lost }} pt</span>
                                        <span class="{{ $finding->isResolved() ? 'badge-success' : 'badge-warning' }}">{{ $finding->isResolved() ? 'Resolved' : 'Open' }}</span>
                                    </div>
                                </div>

                                @if ($finding->photos->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($finding->photos as $photo)
                                            <a href="{{ $photo->url() }}" target="_blank" class="block h-20 w-20 overflow-hidden rounded-control border border-gray-200">
                                                <img src="{{ $photo->url() }}" alt="" loading="lazy" class="h-full w-full object-cover" />
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-3">
                                    <livewire:audits.finding-actions :finding-id="$finding->id" :key="'fa-' . $finding->id" />
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Acknowledgement --}}
@if ($audit->requires_acknowledgement)
    <div class="card mt-4 p-4">
        <h2 class="text-base font-semibold text-gray-900">Acknowledgement</h2>
        @if ($audit->isAcknowledged())
            <div class="mt-2 flex flex-wrap items-center gap-6">
                <div class="text-sm">
                    <p class="text-gray-900">{{ $audit->acknowledged_by_name }}</p>
                    <p class="text-gray-600">{{ $audit->acknowledged_by_position }} · {{ $audit->acknowledged_at?->format('d M Y H:i') }}</p>
                </div>
                @if ($audit->signatureUrl())
                    <img src="{{ $audit->signatureUrl() }}" alt="Signature" class="h-16 rounded-control border border-gray-200 bg-white px-2" />
                @endif
            </div>
        @elseif ($canConduct)
            <p class="help mt-1">The outlet's manager or shift officer signs for the audit as scored. Hand them the tablet.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label" for="ack-name">Name</label>
                    <input id="ack-name" type="text" wire:model="ackName" class="input" />
                    @error('ackName') <p class="error-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="ack-pos">Position</label>
                    <input id="ack-pos" type="text" wire:model="ackPosition" class="input" placeholder="Manager, Chef, Shift Officer" />
                    @error('ackPosition') <p class="error-text">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-3"
                 x-data="{
                    drawing: false, empty: true, ctx: null,
                    init() {
                        const c = this.$refs.pad; c.width = c.offsetWidth * 2; c.height = 320;
                        this.ctx = c.getContext('2d'); this.ctx.scale(2, 2);
                        this.ctx.lineWidth = 2; this.ctx.lineCap = 'round'; this.ctx.strokeStyle = '#111827';
                    },
                    pos(e) { const r = this.$refs.pad.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; },
                    start(e) { this.drawing = true; this.empty = false; const [x, y] = this.pos(e); this.ctx.beginPath(); this.ctx.moveTo(x, y); },
                    move(e) { if (!this.drawing) return; const [x, y] = this.pos(e); this.ctx.lineTo(x, y); this.ctx.stroke(); },
                    stop() { this.drawing = false; },
                    clear() { this.ctx.clearRect(0, 0, this.$refs.pad.width, this.$refs.pad.height); this.empty = true; },
                    submit() { $wire.set('signature', this.empty ? '' : this.$refs.pad.toDataURL('image/png')).then(() => $wire.acknowledge()); }
                 }">
                <label class="label">Signature</label>
                <canvas x-ref="pad" class="mt-1 block h-40 w-full touch-none rounded-control border border-gray-300 bg-white"
                        @pointerdown.prevent="start($event)" @pointermove.prevent="move($event)"
                        @pointerup="stop()" @pointerleave="stop()" @pointercancel="stop()"></canvas>
                <div class="mt-3 flex flex-wrap justify-between gap-2">
                    <button type="button" @click="clear()" class="btn-ghost">Clear</button>
                    <button type="button" @click="submit()" class="btn-primary">Acknowledge audit</button>
                </div>
            </div>
        @else
            <p class="help mt-1">Not yet acknowledged by the outlet.</p>
        @endif
    </div>
@endif
