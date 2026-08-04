<?php

namespace App\Livewire\Clock\Staff;

use App\Models\ClockEvent;
use App\Models\ClockSetting;
use App\Scopes\CompanyScope;
use App\Services\Hr\ClockInException;
use App\Services\Hr\ClockInService;
use App\Services\Hr\ShiftResolver;
use Carbon\Carbon;
use Livewire\Attributes\Locked;

/**
 * The clock-in screen: one big button, the shift you are on, and what
 * happened last time you pressed it.
 *
 * The camera, the face descriptor and the GPS fix are gathered in the
 * browser (resources/js/clock.js) and handed to submit() in one call. This
 * component's job is to hand them straight to ClockInService and render the
 * answer — no judgement of its own, so the staff app and any future kiosk
 * cannot drift apart on what counts as late.
 */
class Punch extends StaffComponent
{
    /** Typed by the employee when they are away from the outlet. */
    public string $reason = '';

    /** The punch just recorded, for the result panel. */
    #[Locked]
    public ?int $lastEventId = null;

    #[Locked]
    public string $errorMessage = '';

    private ?array $shiftCache = null;

    private bool $shiftResolved = false;

    /**
     * @param  array  $payload  Raw observations from the device. Untrusted:
     *                          every value is validated downstream, and none
     *                          of it carries a verdict.
     */
    public function submit(array $payload): void
    {
        $this->errorMessage = '';
        $this->lastEventId  = null;

        // The type is decided HERE, from what is already on record, not from
        // whatever the browser sent. A stale tab offering "Clock in" to
        // somebody who has already clocked in must not be able to open a
        // second shift.
        $type = $this->nextType();

        try {
            $event = app(ClockInService::class)->punch($this->staff(), $type, [
                'latitude'     => $payload['latitude']  ?? null,
                'longitude'    => $payload['longitude'] ?? null,
                'accuracy'     => $payload['accuracy']  ?? null,
                'descriptor'   => $payload['descriptor'] ?? null,
                'selfie'       => is_string($payload['selfie'] ?? null) ? $payload['selfie'] : null,
                'reason'       => $this->reason,
                'device_label' => is_string($payload['device'] ?? null) ? $payload['device'] : null,
                'user_agent'   => request()->userAgent(),
                'ip'           => request()->ip(),
            ]);
        } catch (ClockInException $e) {
            // Refusals are the employee's to act on — shown as written.
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->lastEventId   = $event->id;
        $this->reason        = '';
        $this->shiftResolved = false;
    }


    /**
     * The clock-in this person has not yet clocked out of, if any.
     *
     * Looks at the last day of punches rather than at today's date. Somebody
     * finishing an overnight shift at 2am has an open punch from yesterday,
     * and a date-based check would offer to clock them in all over again.
     */
    public function openPunch(): ?ClockEvent
    {
        $last = ClockEvent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('employee_id', $this->staff()->id)
            ->where('happened_at', '>=', now()->subDay())
            ->counted()
            ->orderByDesc('happened_at')
            ->orderByDesc('id')
            ->first();

        return $last?->type === ClockEvent::TYPE_IN ? $last : null;
    }

    public function nextType(): string
    {
        return $this->openPunch() ? ClockEvent::TYPE_OUT : ClockEvent::TYPE_IN;
    }

    /**
     * @return array{entry: \App\Models\RosterEntry, start: Carbon, end: Carbon}|null
     */
    public function shift(): ?array
    {
        if (! $this->shiftResolved) {
            $this->shiftCache    = app(ShiftResolver::class)->resolve($this->staff(), now(), $this->nextType());
            $this->shiftResolved = true;
        }

        return $this->shiftCache;
    }

    /** The business day the button is currently acting on. */
    public function workDate(): Carbon
    {
        $shift = $this->shift();

        return $shift
            ? Carbon::parse($shift['entry']->day_date->format('Y-m-d'))
            : now()->startOfDay();
    }

    /** Punches already recorded for the shift in play. */
    public function punchesToday()
    {
        return ClockEvent::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('employee_id', $this->staff()->id)
            ->where('work_date', $this->workDate()->toDateString())
            ->counted()
            ->orderBy('happened_at')
            ->get();
    }

    public function render()
    {
        $lastEvent = $this->lastEventId
            ? ClockEvent::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $this->staff()->company_id)
                ->where('employee_id', $this->staff()->id)
                ->find($this->lastEventId)
            : null;

        return view('livewire.clock.staff.punch', [
            'settings'  => ClockSetting::forCompany($this->staff()->company_id),
            'shift'     => $this->shift(),
            'nextType'  => $this->nextType(),
            'punches'   => $this->punchesToday(),
            'lastEvent' => $lastEvent,
            'outlet'    => $this->staff()->outlet,
        ])->layout('layouts.clock-staff', $this->shell('Clock in'));
    }
}
