<?php

namespace App\Livewire\Audits;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditFindingPhoto;
use App\Models\AuditLine;
use App\Models\AuditSection;
use App\Models\AuditTemplateItem;
use App\Models\Employee;
use App\Services\Audits\AuditScoreService;
use App\Services\Audits\AuditService;
use App\Services\ImageStorageService;
use App\Traits\RejectsUnpreviewableUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The audit itself: conducted on a phone, read on a desk.
 *
 * WHILE A DRAFT this is a checklist. One section on screen at a time, every
 * scored line a row with three big targets — OK, NC, N/A — and EVERY TAP
 * SAVES. There is no Save button because the person using this is standing
 * in a kitchen with a tablet in one hand, and a form that can lose an hour of
 * walking when the connection drops in the cold room is a form that goes back
 * to paper. Marking a line NC opens a panel on that row for points lost, a
 * note and photographs; nothing else on the page moves.
 *
 * ONCE SUBMITTED the same URL is the record: the score card, the findings with
 * their photos and corrective actions, and the outlet's sign-off. Every write
 * re-checks `audits.conduct` itself, because the route is gated on view so
 * that a manager can read what was found.
 *
 * Only the CURRENT section's lines are hydrated — a ROSE audit is three
 * hundred lines and a Livewire payload carrying all of them on every tap is
 * the difference between instant and laggy on outlet Wi-Fi.
 */
class Conduct extends Component
{
    use WithFileUploads, RejectsUnpreviewableUploads;

    #[Locked]
    public int $auditId;

    public ?int $sectionId = null;

    /** 'sections' while conducting or browsing; 'summary' once submitted. */
    public string $view = 'sections';

    // Header
    public string $timeIn    = '';
    public string $timeOut   = '';
    public string $reference = '';
    public string $notes     = '';
    /** @var array<string, string|null> field key => value */
    public array $headerValues = [];

    // Per-line text, keyed by line id. Saved on blur.
    /** @var array<int, string> */
    public array $lineNotes = [];
    /** @var array<int, string> */
    public array $subjects = [];
    /** @var array<int, string> */
    public array $infoValues = [];

    /** @var array<int, mixed> line id => pending upload */
    public array $photos = [];

    // Acknowledgement
    public string $ackName     = '';
    public string $ackPosition = '';
    public string $signature   = '';

    public function mount(int $id): void
    {
        $audit = Audit::with('sections')->findOrFail($id);

        abort_unless(Auth::user()->canAccessOutlet($audit->outlet_id), 403);

        $this->auditId   = $audit->id;
        $this->timeIn    = $audit->time_in ? substr($audit->time_in, 0, 5) : '';
        $this->timeOut   = $audit->time_out ? substr($audit->time_out, 0, 5) : '';
        $this->reference = $audit->reference_number ?? '';
        $this->notes     = $audit->notes ?? '';

        foreach ($audit->header_values ?? [] as $field) {
            $this->headerValues[$field['key']] = $field['value'] ?? null;
        }

        $this->view = $audit->isDraft() ? 'sections' : 'summary';

        // Open on the first section still being worked, so coming back to a
        // half-done audit lands where the auditor left off.
        $this->sectionId = $audit->sections->first(fn ($s) => ! $s->isComplete())?->id
            ?? $audit->sections->first()?->id;

        $this->loadSectionText();
    }

    // ── Loading ──────────────────────────────────────────────────────────

    private function audit(): Audit
    {
        return Audit::findOrFail($this->auditId);
    }

    private function section(): ?AuditSection
    {
        return $this->sectionId
            ? AuditSection::where('audit_id', $this->auditId)->find($this->sectionId)
            : null;
    }

    private function line(int $id): AuditLine
    {
        return AuditLine::where('audit_id', $this->auditId)->findOrFail($id);
    }

    private function loadSectionText(): void
    {
        $this->lineNotes = $this->subjects = $this->infoValues = [];

        $section = $this->section();
        if (! $section) return;

        foreach ($section->lines()->get(['id', 'note', 'subject', 'info_value', 'type']) as $line) {
            $this->lineNotes[$line->id]  = $line->note ?? '';
            $this->subjects[$line->id]   = $line->subject ?? '';
            $this->infoValues[$line->id] = $line->info_value ?? '';
        }
    }

    private function requireConduct(): Audit
    {
        abort_unless(Auth::user()?->canDo('audits.conduct'), 403);

        return $this->audit();
    }

    // ── Navigation ───────────────────────────────────────────────────────

    public function selectSection(int $id): void
    {
        $this->sectionId = $id;
        $this->view      = 'sections';
        $this->loadSectionText();
        $this->dispatch('audit-section-changed');
    }

    public function nextSection(): void
    {
        $ids   = AuditSection::where('audit_id', $this->auditId)->orderBy('sort_order')->pluck('id')->all();
        $index = array_search($this->sectionId, $ids, true);

        if ($index !== false && isset($ids[$index + 1])) {
            $this->selectSection($ids[$index + 1]);
        }
    }

    public function showSummary(): void
    {
        $this->view = 'summary';
    }

    // ── Header ───────────────────────────────────────────────────────────

    public function updatedTimeIn(): void    { $this->saveHeader(); }
    public function updatedTimeOut(): void   { $this->saveHeader(); }
    public function updatedReference(): void { $this->saveHeader(); }
    public function updatedNotes(): void     { $this->saveHeader(); }
    public function updatedHeaderValues(): void { $this->saveHeader(); }

    private function saveHeader(): void
    {
        $audit = $this->requireConduct();
        abort_unless($audit->isDraft(), 403);

        $values = collect($audit->header_values ?? [])->map(function ($field) {
            $value = $this->headerValues[$field['key']] ?? null;
            $field['value'] = is_string($value) && trim($value) === '' ? null : $value;

            return $field;
        })->all();

        $audit->update([
            'time_in'          => $this->timeIn ?: null,
            'time_out'         => $this->timeOut ?: null,
            'reference_number' => trim($this->reference) ?: null,
            'notes'            => trim($this->notes) ?: null,
            'header_values'    => $values,
        ]);
    }

    // ── Answering ────────────────────────────────────────────────────────

    public function answer(int $lineId, string $result, AuditService $audits): void
    {
        $this->requireConduct();

        $line = $this->line($lineId);

        // Tapping the selected result again clears it — the way to undo a
        // mis-tap without a fourth button.
        $audits->answer($line, $line->result === $result ? null : $result);
    }

    public function adjustPointsLost(int $lineId, int $delta, AuditService $audits): void
    {
        $this->requireConduct();

        $line = $this->line($lineId);
        $audits->setPointsLost($line, $line->points_lost + $delta);
    }

    public function updatedLineNotes($value, $key): void
    {
        $this->requireConduct();
        app(AuditService::class)->setNote($this->line((int) $key), $value);
    }

    public function updatedSubjects($value, $key): void
    {
        $this->requireConduct();
        app(AuditService::class)->setSubject($this->line((int) $key), $value);
    }

    public function updatedInfoValues($value, $key): void
    {
        $this->requireConduct();
        app(AuditService::class)->setInfo($this->line((int) $key), $value);
    }

    public function markRemainingOk(AuditService $audits): void
    {
        $this->requireConduct();

        $section = $this->section();
        if (! $section) return;

        $count = $audits->markRemainingOk($section);

        session()->flash('success', $count === 1 ? '1 item marked OK.' : "{$count} items marked OK.");
    }

    // ── Photos ───────────────────────────────────────────────────────────

    /**
     * A photograph of a non-conformance, stored the moment it is picked.
     *
     * Keyed by line rather than by finding because the finding is created
     * by the NC tap a second earlier and the view does not know its id yet.
     */
    public function updatedPhotos($value, $key): void
    {
        $audit = $this->requireConduct();
        abort_unless($audit->isDraft(), 403);

        $lineId = (int) $key;
        $file   = $this->keepPreviewableUpload($this->photos[$lineId] ?? null, "photos.{$lineId}");

        unset($this->photos[$lineId]);

        if (! $file) {
            return;
        }

        $this->validate(["photos.{$lineId}" => 'nullable']); // clears stale errors

        try {
            validator(['photo' => $file], ['photo' => ImageStorageService::uploadRule(8192)], [
                'photo.mimes' => ImageStorageService::uploadMessage(),
                'photo.max'   => 'The photo may not be larger than 8 MB.',
            ])->validate();
        } catch (ValidationException $e) {
            $this->addError("photos.{$lineId}", $e->validator->errors()->first());
            return;
        }

        $line    = $this->line($lineId);
        $finding = $line->finding()->withoutGlobalScopes()->first();

        // A photo before the tap: treat it as the tap. Somebody who photographs
        // a line has found something.
        if (! $finding) {
            app(AuditService::class)->answer($line, AuditLine::RESULT_NC);
            $finding = $line->finding()->withoutGlobalScopes()->first();
        }

        if (! $finding || $finding->photos()->count() >= 6) {
            $this->addError("photos.{$lineId}", 'Six photos per finding is the limit.');
            return;
        }

        AuditFindingPhoto::create([
            'audit_finding_id' => $finding->id,
            'file_path'        => ImageStorageService::storeCompressed($file, 'audit-photos/' . $audit->company_id, 'public'),
            'uploaded_by'      => Auth::id(),
        ]);
    }

    public function removePhoto(int $photoId): void
    {
        $audit = $this->requireConduct();
        abort_unless($audit->isDraft(), 403);

        AuditFindingPhoto::whereHas('finding', fn ($q) => $q->where('audit_id', $this->auditId))
            ->findOrFail($photoId)
            ->delete();
    }

    // ── Submitting and after ─────────────────────────────────────────────

    public function submit(AuditService $audits)
    {
        $audit = $this->requireConduct();

        try {
            $audits->submit($audit, Auth::user());
        } catch (ValidationException $e) {
            $this->addError('audit', $e->validator->errors()->first());
            return;
        }

        if (! $this->timeOut) {
            $this->timeOut = now()->format('H:i');
            $audit->update(['time_out' => $this->timeOut]);
        }

        $this->view = 'summary';
        session()->flash('success', 'Audit submitted. Findings are now open for corrective action.');
    }

    public function reopen(AuditService $audits): void
    {
        abort_unless(Auth::user()?->canDo('audits.reopen'), 403);

        $audits->reopen($this->audit());

        $this->view = 'sections';
        $this->loadSectionText();
        session()->flash('success', 'Audit reopened. The outlet will need to acknowledge it again after resubmission.');
    }

    public function acknowledge(AuditService $audits): void
    {
        $this->requireConduct();

        $this->validate([
            'ackName'     => 'required|string|max:120',
            'ackPosition' => 'required|string|max:80',
            'signature'   => 'nullable|string|max:2000000',
        ], [
            'ackName.required'     => 'Who is acknowledging this audit?',
            'ackPosition.required' => 'Their position — Manager, Chef, Shift Officer.',
        ]);

        $audits->acknowledge($this->audit(), trim($this->ackName), trim($this->ackPosition), $this->signature ?: null);

        $this->reset(['ackName', 'ackPosition', 'signature']);
        session()->flash('success', 'Acknowledged.');
    }

    // ── Render ───────────────────────────────────────────────────────────

    public function render()
    {
        $audit = Audit::with(['outlet', 'auditor', 'sections'])->findOrFail($this->auditId);

        $section = $this->section();
        $lines   = collect();
        $photosByLine = collect();

        if ($this->view === 'sections' && $section) {
            $lines = $section->lines()->get();

            $photosByLine = AuditFindingPhoto::query()
                ->join('audit_findings', 'audit_findings.id', '=', 'audit_finding_photos.audit_finding_id')
                ->whereIn('audit_findings.audit_line_id', $lines->pluck('id'))
                ->orderBy('audit_finding_photos.id')
                ->get(['audit_finding_photos.*', 'audit_findings.audit_line_id'])
                ->groupBy('audit_line_id');
        }

        $findings = collect();
        if ($this->view === 'summary') {
            $findings = AuditFinding::with(['photos', 'actions.owner', 'actions.verifiedBy', 'line.parent'])
                ->where('audit_id', $audit->id)
                ->orderBy('id')
                ->get()
                ->sortBy(fn ($f) => $f->line?->sort_order ?? 0)
                ->groupBy('section_name');
        }

        $employees = Employee::where('outlet_id', $audit->outlet_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'designation']);

        $sectionIds = $audit->sections->pluck('id')->values();
        $pos        = $sectionIds->search($this->sectionId);

        return view('livewire.audits.conduct', [
            'audit'        => $audit,
            'section'      => $section,
            'lines'        => $lines,
            'photosByLine' => $photosByLine,
            'findings'     => $findings,
            'employees'    => $employees,
            'isDraft'      => $audit->isDraft(),
            'canConduct'   => Auth::user()->canDo('audits.conduct'),
            'canActions'   => Auth::user()->canDo('audits.actions.manage'),
            'canReopen'    => Auth::user()->canDo('audits.reopen'),
            'isLastSection' => $pos !== false && $pos === $sectionIds->count() - 1,
            'unanswered'   => app(AuditScoreService::class)->unanswered($audit),
            'bands'        => [
                'good' => 'text-success-700', 'fair' => 'text-warning-700', 'poor' => 'text-danger-700', 'none' => 'text-gray-500',
            ],
        ])->layout(\App\Helpers\WorkspaceLayout::get(), [
            'title' => $audit->template_code ?: $audit->template_name,
        ]);
    }
}
