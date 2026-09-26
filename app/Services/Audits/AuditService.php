<?php

namespace App\Services\Audits;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditLine;
use App\Models\AuditSchedule;
use App\Models\AuditSection;
use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\CorrectiveAction;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The life of an audit: start it from a template, record what was found,
 * submit it, have the outlet sign it, close it when the fixes are verified.
 *
 * Every write to a line goes through here so the finding that shadows an NC
 * line and the cached score stay in step with it — see answer().
 */
class AuditService
{
    public function __construct(private AuditScoreService $scores)
    {
    }

    // ── Starting ─────────────────────────────────────────────────────────

    /**
     * Copy the template into a new draft audit.
     *
     * The copy is the whole point: see the `audits` migration. Items are
     * walked in section order, parents before children, and each line keeps
     * the template item id only as provenance.
     */
    public function start(AuditTemplate $template, Outlet $outlet, User $auditor, string $date, array $attrs = []): Audit
    {
        $template->loadMissing('sections.items');

        return DB::transaction(function () use ($template, $outlet, $auditor, $date, $attrs) {
            $audit = Audit::create(array_merge([
                'company_id'               => $template->company_id,
                'outlet_id'                => $outlet->id,
                'audit_template_id'        => $template->id,
                'template_name'            => $template->name,
                'template_code'            => $template->code,
                'alt_language'             => $template->alt_language,
                'template_version'         => $template->version,
                'status'                   => Audit::STATUS_DRAFT,
                'audit_date'               => $date,
                'auditor_id'               => $auditor->id,
                'header_values'            => collect($template->headerFieldList())
                    ->map(fn ($f) => $f + ['value' => null])->all(),
                'requires_acknowledgement' => $template->requires_acknowledgement,
                'outcome_rules'            => $template->outcomeRules(),
                'created_by'               => $auditor->id,
            ], $attrs));

            foreach ($template->sections as $sectionOrder => $tSection) {
                $section = AuditSection::create([
                    'audit_id'     => $audit->id,
                    'name'         => $tSection->name,
                    'name_alt'     => $tSection->name_alt,
                    'scoring_mode' => $tSection->scoring_mode,
                    'sort_order'   => $sectionOrder,
                ]);

                $this->copyItems($audit, $section, $tSection->items);
            }

            $this->scores->recalculate($audit);

            return $audit;
        });
    }

    /**
     * Start the audit a schedule says is due, and roll the schedule forward.
     *
     * The roll happens at START, not at submit: the point of the schedule is
     * "has somebody gone", and a draft audit is somebody having gone. A draft
     * later deleted leaves the schedule advanced; the Schedules screen shows
     * the last audit so that is visible, and the due date can be edited.
     */
    public function startFromSchedule(AuditSchedule $schedule, User $auditor, string $date, array $attrs = []): Audit
    {
        $schedule->loadMissing(['template', 'outlet']);

        abort_unless($schedule->template && $schedule->outlet, 422, 'This schedule points at a form or outlet that no longer exists.');

        return DB::transaction(function () use ($schedule, $auditor, $date, $attrs) {
            $audit = $this->start($schedule->template, $schedule->outlet, $auditor, $date, $attrs + [
                'audit_schedule_id' => $schedule->id,
            ]);

            $schedule->forceFill([
                'last_audit_id'   => $audit->id,
                'last_started_on' => $date,
                'next_due_on'     => $schedule->nextDueAfter($schedule->next_due_on),
            ])->save();

            return $audit;
        });
    }

    /**
     * @param \Illuminate\Support\Collection<int, AuditTemplateItem> $items every item in the section
     */
    private function copyItems(Audit $audit, AuditSection $section, $items): void
    {
        $byParent = $items->groupBy(fn ($i) => $i->parent_id ?? 0);
        $order    = 0;

        $walk = function (?int $templateParentId, ?int $lineParentId) use (&$walk, &$order, $byParent, $audit, $section) {
            foreach ($byParent->get($templateParentId ?? 0, collect()) as $item) {
                $hasChildren = $byParent->has($item->id);

                $line = AuditLine::create([
                    'audit_id'         => $audit->id,
                    'audit_section_id' => $section->id,
                    'parent_id'        => $lineParentId,
                    'template_item_id' => $item->id,
                    'number'           => $item->number,
                    'label'            => $item->label,
                    'label_alt'        => $item->label_alt,
                    'hint'             => $item->hint,
                    'type'             => $item->type,
                    'info_type'        => $item->info_type,
                    // A parent's points are its children's; it holds none of its own.
                    'points'           => $hasChildren ? 0 : $item->points,
                    'is_leaf'          => ! $hasChildren,
                    'sort_order'       => $order++,
                ]);

                if ($hasChildren) {
                    $walk($item->id, $line->id);
                }
            }
        };

        $walk(null, null);
    }

    // ── Conducting ───────────────────────────────────────────────────────

    /**
     * Record a result on a leaf. NC opens (or keeps) a finding; anything else
     * closes it. The finding is created NOW, while the audit is a draft, so a
     * photo taken at the chiller has something to attach to.
     */
    public function answer(AuditLine $line, ?string $result, ?int $pointsLost = null): AuditLine
    {
        $this->assertEditable($line->audit);
        abort_unless($line->isScorable(), 422);

        if ($result !== null && ! in_array($result, AuditLine::RESULTS, true)) {
            throw ValidationException::withMessages(['result' => 'Unknown result.']);
        }

        DB::transaction(function () use ($line, $result, $pointsLost) {
            $isNc = $result === AuditLine::RESULT_NC;

            $line->forceFill([
                'result'      => $result,
                'points_lost' => $isNc
                    ? min($line->points, max(0, $pointsLost ?? ($line->isNonConformance() ? $line->points_lost : $line->points)))
                    : 0,
            ])->save();

            if ($isNc) {
                $this->openFinding($line);
            } else {
                // Changing one's mind removes the finding — and its photos and
                // actions with it. While a draft, nothing on it is a record yet.
                $line->finding()->withoutGlobalScopes()->get()->each->delete();
            }

            $this->scores->recalculate($line->audit);
        });

        return $line->refresh();
    }

    public function setPointsLost(AuditLine $line, int $pointsLost): AuditLine
    {
        $this->assertEditable($line->audit);
        abort_unless($line->isNonConformance(), 422);

        $pointsLost = min($line->points, max(0, $pointsLost));

        $line->forceFill(['points_lost' => $pointsLost])->save();
        $line->finding()->update(['points_lost' => $pointsLost]);
        $this->scores->recalculate($line->audit);

        return $line;
    }

    /** The auditor's note on a line; mirrored onto the finding when there is one. */
    public function setNote(AuditLine $line, ?string $note): void
    {
        $this->assertEditable($line->audit);

        $note = trim((string) $note) ?: null;
        $line->forceFill(['note' => $note])->save();
        $line->finding()->update(['description' => $note]);
    }

    public function setSubject(AuditLine $line, ?string $subject): void
    {
        $this->assertEditable($line->audit);
        abort_unless($line->type === AuditTemplateItem::TYPE_PRODUCT, 422);

        $line->forceFill(['subject' => trim((string) $subject) ?: null])->save();

        // Findings under this slot are labelled by the product, so a rename
        // has to reach them.
        foreach ($line->children()->with('finding')->get() as $child) {
            $child->finding?->update(['item_label' => $this->findingLabel($child)]);
        }
    }

    public function setInfo(AuditLine $line, ?string $value): void
    {
        $this->assertEditable($line->audit);
        abort_unless($line->type === AuditTemplateItem::TYPE_INFO, 422);

        $line->forceFill(['info_value' => trim((string) $value) ?: null])->save();
    }

    /**
     * Every unanswered leaf in a section becomes OK.
     *
     * An auditor ticks failures, not passes — on a 300-line form, tapping OK
     * two hundred and eighty times is the thing that would make them go back
     * to paper. This is the one bulk write in the module and it only ever
     * touches lines with no result at all.
     */
    public function markRemainingOk(AuditSection $section): int
    {
        $this->assertEditable($section->audit);

        $count = $section->lines()
            ->where('is_leaf', true)
            ->where('type', '!=', AuditTemplateItem::TYPE_INFO)
            ->whereNull('result')
            ->update(['result' => AuditLine::RESULT_OK, 'points_lost' => 0]);

        $this->scores->recalculate($section->audit);

        return $count;
    }

    private function openFinding(AuditLine $line): AuditFinding
    {
        $audit = $line->audit;

        return AuditFinding::withoutGlobalScopes()->updateOrCreate(
            ['audit_line_id' => $line->id],
            [
                'company_id'   => $audit->company_id,
                'outlet_id'    => $audit->outlet_id,
                'audit_id'     => $audit->id,
                'severity'     => $line->section->isPenalty()
                    ? AuditFinding::SEVERITY_MAJOR
                    : AuditFinding::SEVERITY_MINOR,
                'section_name' => $line->section->name,
                'item_label'   => $this->findingLabel($line),
                'points_lost'  => $line->points_lost,
                'description'  => $line->note,
            ]
        );
    }

    /**
     * "1a · Bunn Coffee Maker" or, under a product slot, "Toast — Kaya & butter · Correct tools".
     * The label the summary lists show, worked out once so it never has to
     * join back through the lines.
     */
    public function findingLabel(AuditLine $line): string
    {
        $parent = $line->parent;
        $parts  = [];

        if ($parent) {
            $parts[] = $parent->type === AuditTemplateItem::TYPE_PRODUCT && $parent->subject
                ? $parent->label . ' — ' . $parent->subject
                : trim(($parent->number ? $parent->number . '. ' : '') . $parent->label);
        }

        $parts[] = trim(($line->number ? $line->number . '. ' : '') . $line->label);

        return \Illuminate\Support\Str::limit(implode(' · ', $parts), 490, '…');
    }

    // ── Submitting and after ─────────────────────────────────────────────

    /**
     * Lock the audit. Every scorable leaf needs a result first: a line
     * nobody looked at is not a pass, and the score would be a lie.
     */
    public function submit(Audit $audit, User $by): Audit
    {
        $this->assertEditable($audit);

        $missing = $this->scores->unanswered($audit);

        if ($missing > 0) {
            throw ValidationException::withMessages([
                'audit' => $missing === 1
                    ? 'One item has no result yet. Mark it OK, NC or N/A before submitting.'
                    : "{$missing} items have no result yet. Use \"Mark the rest OK\" on each section, or answer them, before submitting.",
            ]);
        }

        $this->scores->recalculate($audit);

        $audit->forceFill([
            'status'       => Audit::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ])->save();

        return $audit;
    }

    /**
     * Back to draft. Its own ability (`audits.reopen`) because it un-does a
     * sign-off: the acknowledgement is cleared, since the outlet signed for
     * the scores as they were, not as they are about to become. Findings and
     * their actions stay — reopening is for a mis-tap, not for erasing what
     * was found.
     */
    public function reopen(Audit $audit): Audit
    {
        abort_if($audit->isDraft(), 422);

        $signature = $audit->signature_path;

        $audit->forceFill([
            'status'                   => Audit::STATUS_DRAFT,
            'submitted_at'             => null,
            'acknowledged_at'          => null,
            'acknowledged_by_name'     => null,
            'acknowledged_by_position' => null,
            'signature_path'           => null,
            'closed_at'                => null,
        ])->save();

        if ($signature) {
            Storage::disk('local')->delete($signature);
        }

        return $audit;
    }

    /**
     * The outlet signs for the audit: a name, a position, and a drawn
     * signature (a PNG data URL from the canvas). The image goes to the
     * private disk — it is a signature.
     */
    public function acknowledge(Audit $audit, string $name, string $position, ?string $signatureDataUrl): Audit
    {
        abort_unless($audit->status === Audit::STATUS_SUBMITTED, 422);

        $path = null;

        if ($signatureDataUrl && preg_match('#^data:image/png;base64,(.+)$#s', $signatureDataUrl, $m)) {
            $binary = base64_decode($m[1], true);

            if ($binary !== false && strlen($binary) > 100 && strlen($binary) < 2 * 1024 * 1024) {
                $path = 'audit-signatures/' . $audit->company_id . '/' . $audit->id . '-' . bin2hex(random_bytes(6)) . '.png';
                Storage::disk('local')->put($path, $binary);
            }
        }

        $audit->forceFill([
            'status'                   => Audit::STATUS_ACKNOWLEDGED,
            'acknowledged_at'          => now(),
            'acknowledged_by_name'     => $name,
            'acknowledged_by_position' => $position,
            'signature_path'           => $path,
        ])->save();

        $this->closeIfResolved($audit);

        return $audit;
    }

    /**
     * An audit closes itself when there is nothing left to do: it has been
     * acknowledged (or never needed to be) and every finding is resolved.
     * Called after each action changes, so nobody has to remember to.
     */
    public function closeIfResolved(Audit $audit): void
    {
        $audit->refresh();

        if ($audit->isDraft() || $audit->isClosed()) {
            return;
        }

        if ($audit->requires_acknowledgement && ! $audit->isAcknowledged()) {
            return;
        }

        $open = $audit->findings()->where('status', AuditFinding::STATUS_OPEN)->exists();

        if (! $open) {
            $audit->forceFill(['status' => Audit::STATUS_CLOSED, 'closed_at' => now()])->save();
        } elseif ($audit->status === Audit::STATUS_CLOSED) {
            $audit->forceFill(['status' => Audit::STATUS_ACKNOWLEDGED, 'closed_at' => null])->save();
        }
    }

    /** A verified action can be un-verified or a new one added, so closure can reverse. */
    public function reviewClosure(AuditFinding $finding): void
    {
        $audit = $finding->audit()->withoutGlobalScopes()->first();

        if (! $audit) {
            return;
        }

        if ($audit->isClosed() && $audit->findings()->where('status', AuditFinding::STATUS_OPEN)->exists()) {
            $audit->forceFill(['status' => Audit::STATUS_ACKNOWLEDGED, 'closed_at' => null])->save();

            return;
        }

        $this->closeIfResolved($audit);
    }

    // ── Guards ───────────────────────────────────────────────────────────

    private function assertEditable(Audit $audit): void
    {
        abort_unless($audit->isDraft(), 403, 'This audit has been submitted and can no longer be changed.');
    }

    /** @return array<string, string> status => label, for filters */
    public static function actionStatuses(): array
    {
        return CorrectiveAction::STATUSES;
    }
}
