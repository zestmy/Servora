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

    /** Punch open in the detail panel. */
    #[Locked]
    public ?int $viewingId = null;

    public string $reviewNote = '';

    /** Chargeable minutes a manager is substituting for the computed figure. */
    public string $overrideMinutes = '';

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
        if (in_array($property, ['search', 'outletFilter', 'statusFilter', 'from', 'to'], true)) {
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
        $this->overrideMinutes = $event->override_late_minutes !== null
            ? (string) $event->override_late_minutes
            : '';
    }

    public function closePanel(): void
    {
        $this->viewingId       = null;
        $this->reviewNote      = '';
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
     * `can_delete_records` is the capability the company admin and business
     * manager roles carry, and the same gate duty rosters and overtime claims
     * already use — deliberately not a new permission, because a fourth way
     * to express "the boss can remove things" is a fourth thing to keep in
     * step. hasCapability() also lets system roles through, as everywhere.
     *
     * Reviewing a punch and deleting one are different powers on purpose: an
     * outlet manager with hr.clock can approve and reject all day and still
     * cannot make a record disappear.
     */
    public function canDelete(): bool
    {
        return Auth::user()?->hasCapability('can_delete_records') ?? false;
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
        session()->flash('success', $this->canViewPay() && (float) $event->penalty_amount > 0
            ? sprintf(
                'Punch deleted. The RM%s late charge it carried no longer applies.',
                number_format((float) $event->penalty_amount, 2)
            )
            : 'Punch deleted.');
    }

    private function findEvent(int $id): ?ClockEvent
    {
        return ClockEvent::whereIn('outlet_id', $this->accessibleOutletIds() ?: [0])
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

        $events = ClockEvent::with(['employee', 'outlet', 'rosterEntry', 'reviewer'])
            ->whereIn('outlet_id', $accessible ?: [0])
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->when($this->outletFilter !== '', fn ($q) => $q->where('outlet_id', (int) $this->outletFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
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
