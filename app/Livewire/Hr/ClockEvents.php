<?php

namespace App\Livewire\Hr;

use App\Models\ClockEvent;
use App\Models\ClockSetting;
use App\Models\Employee;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The clock-in review queue.
 *
 * Opens on the punches that need a decision, because that is the only part
 * of this screen with a deadline — everything else is a log, and a log can
 * wait. Approving or rejecting is one press; the detail panel exists for the
 * ones where a manager wants to see the selfie and the metres before
 * deciding.
 */
class ClockEvents extends Component
{
    use WithPagination;

    public string $search        = '';
    public string $outletFilter  = '';
    public string $statusFilter  = ClockEvent::STATUS_FLAGGED;
    public string $from          = '';
    public string $to            = '';

    /**
     * Kiosk, own device, or both.
     *
     * The question this answers is the one an outlet running a kiosk actually
     * has: who is still punching on their phone. That is visible as a filter
     * rather than only as a flag because the interesting cases are the ones
     * that were NOT flagged — somebody with a standing exception using it
     * every single day is a permission worth revisiting, and no individual
     * punch of theirs will ever appear in the review queue to say so.
     */
    public string $sourceFilter  = '';

    /**
     * Whether deleted punches are on screen: '' hides them, 'include' mixes
     * them in, 'only' shows nothing else.
     *
     * A separate control from the status filter because the two are
     * independent — a deleted punch still has a status, and folding "deleted"
     * into that list would make it impossible to ask for deleted FLAGGED
     * punches, which is the actual question somebody has when hunting for
     * one they removed by mistake.
     */
    public string $deletedFilter = '';

    /** Punch open in the detail panel. */
    #[Locked]
    public ?int $viewingId = null;

    public string $reviewNote = '';

    /** Chargeable minutes a manager is substituting for the computed figure. */
    public string $overrideMinutes = '';

    /**
     * Why a late charge is being written off.
     *
     * Its own field rather than reuse of $reviewNote, which the employee sees
     * when a punch is rejected. These are two different sentences written to
     * two different readers — "your punch was outside the geofence" and "car
     * broke down on the LDP, charge waived" — and one box collecting both
     * would either leak a manager's private note or lose the record of why
     * money was forgiven.
     */
    public string $waiveReason = '';

    public function mount(): void
    {
        $user = Auth::user();

        if ($this->outletFilter === '' && $user->activeOutletId()) {
            $this->outletFilter = (string) $user->activeOutletId();
        }

        // A fortnight, not a month: this queue is worked daily, and a default
        // that pages through four weeks of verified punches buries the six
        // that actually need someone.
        $this->from = now()->subDays(13)->format('Y-m-d');
        $this->to   = now()->format('Y-m-d');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'outletFilter', 'statusFilter', 'sourceFilter', 'deletedFilter', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    protected function accessibleOutletIds(): array
    {
        return Auth::user()->accessibleOutletIds();
    }

    public function view(int $id): void
    {
        $event = $this->findEvent($id);

        if (! $event) {
            return;
        }

        $this->viewingId       = $event->id;
        $this->reviewNote      = (string) $event->review_note;
        $this->waiveReason     = (string) $event->lateness_waive_reason;
        $this->overrideMinutes = $event->override_late_minutes !== null
            ? (string) $event->override_late_minutes
            : '';
    }

    public function closePanel(): void
    {
        $this->viewingId       = null;
        $this->reviewNote      = '';
        $this->waiveReason     = '';
        $this->overrideMinutes = '';
    }

    public function approve(int $id): void
    {
        $this->decide($id, ClockEvent::STATUS_APPROVED);
    }

    public function reject(int $id): void
    {
        $this->decide($id, ClockEvent::STATUS_REJECTED);
    }

    /**
     * Whether this user may write off a late charge.
     *
     * Its own ability, not implied by hr.clock or by being able to see pay.
     * Approving a flagged punch says it is genuine; this says the money
     * attached to a genuine punch will not be collected, and the two are not
     * the same trust. canDo() lets system roles through, as everywhere.
     */
    public function canWaiveLateness(): bool
    {
        return Auth::user()->can('hr.clock.waive_lateness');
    }

    /**
     * Forgive the late charge on one punch.
     *
     * Leaves minutes_late, chargeable_late_minutes and penalty_amount exactly
     * as they were. The punch goes on saying the person was late and what it
     * would have cost; only what is COLLECTED changes, and it changes through
     * ClockEvent::chargeableAmount() rather than by rewriting the figure. A
     * waiver that zeroed penalty_amount would be indistinguishable a month
     * later from a punch that was never late, which is precisely the question
     * anybody auditing waivers is asking.
     */
    public function waiveLateness(int $id): void
    {
        abort_unless($this->canWaiveLateness(), 403);

        $event = $this->findEvent($id);

        if (! $event) {
            return;
        }

        if ($event->trashed()) {
            // A deleted punch already costs nothing. Waiving one would write a
            // decision onto a record that counts for nothing and leave a
            // waiver in the log for money nobody was going to collect.
            session()->flash('error', 'That punch is deleted, so it carries no charge to waive.');

            return;
        }

        if (! $event->hasLatenessCharge()) {
            session()->flash('error', 'There is no late charge on that punch to waive.');

            return;
        }

        $reason = trim($this->waiveReason);

        if ($reason === '') {
            // The reason IS the feature. A waiver with none is a mis-click
            // that nobody can tell apart from a decision three weeks later.
            $this->addError('waiveReason', 'Say why the charge is being waived.');

            return;
        }

        $event->update([
            'lateness_waived_at'    => now(),
            'lateness_waived_by'    => Auth::id(),
            'lateness_waive_reason' => mb_substr($reason, 0, 500),
        ]);

        session()->flash('success', $this->canViewPay()
            ? sprintf('RM%s late charge waived.', number_format((float) $event->penalty_amount, 2))
            : 'Late charge waived.');
    }

    /**
     * Put a waived charge back.
     *
     * Nothing has to be recomputed, which is the payoff for never having
     * overwritten the figure: clearing the three columns restores exactly the
     * charge the clock worked out at the time, at the rate then in force,
     * rather than re-pricing it against whatever the rate is today.
     */
    public function restoreLatenessCharge(int $id): void
    {
        abort_unless($this->canWaiveLateness(), 403);

        $event = $this->findEvent($id);

        if (! $event || ! $event->latenessWaived()) {
            return;
        }

        $event->update([
            'lateness_waived_at'    => null,
            'lateness_waived_by'    => null,
            'lateness_waive_reason' => null,
        ]);

        $this->waiveReason = '';

        session()->flash('success', $this->canViewPay()
            ? sprintf('RM%s late charge applies again.', number_format((float) $event->penalty_amount, 2))
            : 'Late charge applies again.');
    }

    /**
     * Record a decision, and re-price the lateness if the manager changed it.
     *
     * The recomputation uses TODAY's rate rather than the one in force when
     * the punch happened. That is the honest reading of a manual override: a
     * manager is deciding what to charge now, with the policy now in force,
     * not reconstructing an old one.
     */
    private function decide(int $id, string $status): void
    {
        abort_unless(Auth::user()->can('hr.clock'), 403);

        $event = $this->findEvent($id);

        if (! $event) {
            return;
        }

        // findEvent() sees deleted punches so they can be opened and
        // restored. Approving one would write a decision onto a record that
        // counts for nothing, so it is refused rather than silently allowed.
        if ($event->trashed()) {
            session()->flash('error', 'That punch is deleted. Restore it first.');

            return;
        }

        $attributes = [
            'status'      => $status,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => trim($this->reviewNote) ?: null,
        ];

        if ($this->canViewPay()) {
            $override = trim($this->overrideMinutes);

            if ($override !== '' && is_numeric($override)) {
                $minutes = max(0, min(65535, (int) $override));
                $settings = ClockSetting::forCompany($event->company_id);

                $penalty = $minutes * (float) $settings->late_rate_per_minute;

                if ($settings->late_cap_per_shift !== null) {
                    $penalty = min($penalty, (float) $settings->late_cap_per_shift);
                }

                $attributes['override_late_minutes'] = $minutes;
                $attributes['penalty_amount']        = round($penalty, 2);
            } elseif ($override === '' && $event->override_late_minutes !== null) {
                // Cleared: fall back to what the clock originally worked out.
                $settings = ClockSetting::forCompany($event->company_id);

                $penalty = $event->chargeable_late_minutes * (float) $settings->late_rate_per_minute;

                if ($settings->late_cap_per_shift !== null) {
                    $penalty = min($penalty, (float) $settings->late_cap_per_shift);
                }

                $attributes['override_late_minutes'] = null;
                $attributes['penalty_amount']        = round($penalty, 2);
            }
        }

        // A rejected punch is void, and a void punch cannot cost money.
        if ($status === ClockEvent::STATUS_REJECTED) {
            $attributes['penalty_amount'] = 0;
        }

        $event->update($attributes);

        $this->closePanel();
        session()->flash('success', $status === ClockEvent::STATUS_REJECTED
            ? 'Punch rejected.'
            : 'Punch approved.');
    }

    /**
     * Whether this user may delete punches at all.
     *
     * This used to be `can_delete_records`, one global flag shared with Sales,
     * Purchasing, Inventory and Overtime Claims — so granting the right to void a
     * mistaken punch also granted the right to delete purchase orders. Phase 1 split
     * it per module; canDo() still lets system roles through, as everywhere.
     *
     * Reviewing a punch and deleting one are different powers on purpose: an
     * outlet manager with hr.clock can approve and reject all day and still
     * cannot make a record disappear.
     */
    public function canDelete(): bool
    {
        return Auth::user()?->canDo('hr.clock.delete') ?? false;
    }

    /**
     * Remove a punch from every total it feeds, keeping the record.
     *
     * It soft deletes, so the row survives for audit while leaving the review
     * queue, the attendance export and the service charge together — see the
     * ClockEvent model for why that single mechanism covers all three.
     *
     * The selfie is deliberately NOT deleted. The photograph is the evidence
     * for a punch somebody has just decided was wrong, which is the moment it
     * is most likely to be asked about, and a restored record with no picture
     * would be worse than useless.
     */
    public function deleteEvent(int $id): void
    {
        abort_unless(Auth::user()->can('hr.clock'), 403);

        if (! $this->canDelete()) {
            session()->flash('error', 'Deleting clock-ins needs company admin access.');

            return;
        }

        $event = $this->findEvent($id);

        if (! $event) {
            return;
        }

        // Written before the delete: an update() afterwards would have to go
        // looking for a row the query builder now hides.
        $event->forceFill(['deleted_by' => Auth::id()])->saveQuietly();
        $event->delete();

        $this->closePanel();

        // Names the money, because that is the part a manager cannot see
        // undone from this screen and would otherwise learn about at payout.
        session()->flash('success', $this->canViewPay() && $event->chargeableAmount() > 0
            ? sprintf(
                'Punch deleted. The RM%s late charge it carried no longer applies.',
                number_format($event->chargeableAmount(), 2)
            )
            : 'Punch deleted.');
    }

    /**
     * Put a deleted punch back, with everything it counted towards.
     *
     * deleted_by is cleared rather than kept: a live record carrying "deleted
     * by Aisha" reads as a record that is still deleted, and the column
     * exists to describe the current state. The history of who removed it and
     * who put it back is not lost — ClockEvent is registered in config/audit,
     * so the observer writes both the deletion and the restore to the audit
     * trail, which is append-only and cannot be edited.
     */
    public function restoreEvent(int $id): void
    {
        abort_unless(Auth::user()->can('hr.clock'), 403);

        if (! $this->canDelete()) {
            session()->flash('error', 'Restoring clock-ins needs company admin access.');

            return;
        }

        $event = $this->findEvent($id);

        if (! $event?->trashed()) {
            return;
        }

        $event->restore();
        $event->forceFill(['deleted_by' => null])->saveQuietly();

        // chargeableAmount(), so a punch whose charge was waived before it was
        // deleted does not announce that the charge is back. It is not.
        session()->flash('success', $this->canViewPay() && $event->chargeableAmount() > 0
            ? sprintf(
                'Punch restored. Its RM%s late charge applies again.',
                number_format($event->chargeableAmount(), 2)
            )
            : 'Punch restored.');
    }

    /**
     * withTrashed, always.
     *
     * Deleting a punch keeps the detail panel open on it for a moment, and
     * restoring one has to find it in the first place. Without this the panel
     * blanked the instant a punch was deleted, and a restore button would
     * have had nothing to act on.
     *
     * Safe because it is the only lookup and every caller re-checks what it
     * is allowed to do — the outlet scope below is what actually guards it.
     */
    private function findEvent(int $id): ?ClockEvent
    {
        return ClockEvent::withTrashed()
            ->whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
            ->find($id);
    }

    /** Penalty figures are pay data — same gate as salary and service points. */
    public function canViewPay(): bool
    {
        return Employee::canViewPay();
    }

    private function period(): array
    {
        try {
            $from = Carbon::parse($this->from)->startOfDay();
            $to   = Carbon::parse($this->to)->startOfDay();
        } catch (\Throwable $e) {
            $from = now()->subDays(13)->startOfDay();
            $to   = now()->startOfDay();
        }

        if ($to->lt($from)) {
            $to = $from->copy();
        }

        return [$from, $to];
    }

    public function render()
    {
        [$from, $to] = $this->period();

        $accessible = $this->accessibleOutletIds();

        // `device` is eager-loaded because sourceDetail() names the kiosk on
        // every kiosk row — thirty rows would otherwise be thirty queries.
        $events = ClockEvent::with(['employee', 'outlet', 'rosterEntry', 'reviewer', 'device'])
            // Deleted punches are absent unless asked for. 'only' is how
            // somebody finds one they removed by mistake without having to
            // remember which day it was on.
            ->when($this->deletedFilter === 'include', fn ($q) => $q->withTrashed())
            ->when($this->deletedFilter === 'only', fn ($q) => $q->onlyTrashed())
            ->whereIn('outlet_id', $accessible ?: [0])
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->when($this->outletFilter !== '', fn ($q) => $q->where('outlet_id', (int) $this->outletFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->sourceFilter !== '', fn ($q) => $q->where('source', $this->sourceFilter))
            ->when($this->search !== '', fn ($q) => $q->whereHas('employee', fn ($e) => $e
                ->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('staff_id', 'like', '%' . $this->search . '%')))
            ->orderByDesc('work_date')
            ->orderByDesc('happened_at')
            ->paginate(30);

        return view('livewire.hr.clock-events', [
            'events'  => $events,
            'outlets' => Outlet::whereIn('id', $accessible ?: [0])->orderBy('name')->get(),
            'viewing' => $this->viewingId ? $this->findEvent($this->viewingId) : null,
            'pending' => ClockEvent::whereIn('outlet_id', $accessible ?: [0])
                ->needingReview()
                ->count(),
        ])->layout('layouts.app', ['title' => 'Clock-Ins']);
    }
}
