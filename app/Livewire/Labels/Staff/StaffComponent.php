<?php

namespace App\Livewire\Labels\Staff;

use App\Models\Employee;
use App\Models\LabelPrinter;
use App\Scopes\CompanyScope;
use App\Services\Labels\LabelStaffSession;
use Livewire\Component;

/**
 * Shared base for the staff label screens.
 *
 * These run with NO authenticated web user, so CompanyScope resolves to
 * nothing and every query has to be scoped by hand to the signed-in
 * employee's company. Getting that wrong would either show a chef an empty
 * screen or, worse, another company's data — so scoping lives here rather
 * than being re-implemented per screen.
 */
abstract class StaffComponent extends Component
{
    public ?int $printerId = null;

    /** Resolved once per request. */
    private ?Employee $staffCache = null;

    public function staff(): Employee
    {
        if ($this->staffCache) {
            return $this->staffCache;
        }

        $employee = app(LabelStaffSession::class)->employee($this->companyId());

        // The middleware already redirected if there was no session, so
        // reaching here without one means it went stale mid-request.
        abort_unless($employee, 403, 'Sign in again.');

        return $this->staffCache = $employee;
    }

    public function companyId(): ?int
    {
        return app()->bound('currentCompany') ? app('currentCompany')->id : null;
    }

    /** Labels are outlet-scoped; the staff member's outlet decides which. */
    public function outletId(): ?int
    {
        return $this->staff()->outlet_id;
    }

    public function outletName(): string
    {
        return (string) ($this->staff()->outlet?->name ?? '');
    }

    /** Printers for this outlet, unscoped because there is no web user. */
    public function printers()
    {
        return LabelPrinter::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->staff()->company_id)
            ->where('outlet_id', $this->outletId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function resolvePrinter(): ?LabelPrinter
    {
        $printers = $this->printers();

        if ($this->printerId) {
            $chosen = $printers->firstWhere('id', $this->printerId);

            if ($chosen) {
                return $chosen;
            }
        }

        return $printers->first();
    }

    protected function mountStaffDefaults(): void
    {
        $this->printerId = $this->resolvePrinter()?->id;
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
