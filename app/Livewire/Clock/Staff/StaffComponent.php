<?php

namespace App\Livewire\Clock\Staff;

use App\Models\Employee;
use App\Services\Staff\StaffSession;
use Livewire\Component;

/**
 * Shared base for the staff clock-in screens.
 *
 * These run with NO authenticated web user, so CompanyScope resolves from
 * the subdomain at best and matches nothing at worst. Every query is scoped
 * by hand to the signed-in employee's company; getting that wrong would
 * either show an empty screen or, far worse, another company's staff.
 */
abstract class StaffComponent extends Component
{
    /** Resolved once per request. */
    private ?Employee $staffCache = null;

    public function staff(): Employee
    {
        if ($this->staffCache) {
            return $this->staffCache;
        }

        $employee = app(StaffSession::class)->employee($this->companyId());

        // The middleware already redirected if there was no session, so
        // reaching here without one means it went stale mid-request.
        abort_unless($employee, 403, 'Sign in again.');

        return $this->staffCache = $employee;
    }

    public function companyId(): ?int
    {
        return app(StaffSession::class)->companyId();
    }

    public function outletName(): string
    {
        return (string) ($this->staff()->outlet?->name ?? '');
    }

    /** Layout data every screen passes up to the shell. */
    protected function shell(string $title): array
    {
        return [
            'staff'      => $this->staff(),
            'outletName' => $this->outletName(),
            'title'      => $title,
        ];
    }
}
