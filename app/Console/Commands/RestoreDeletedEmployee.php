<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild an employee that was HARD-deleted, from the audit trail.
 *
 * This exists for the staff removed before `employees` learned to soft-delete
 * (migration 2026_08_13_000060). Since that migration the staff list has a
 * Restore button and this command has nothing to do — a soft-deleted employee
 * never lost anything and is put back from the screen.
 *
 * WHAT IT CAN AND CANNOT GIVE BACK, stated plainly because the difference
 * decides whether somebody also needs to go and find a database backup:
 *
 *   RECOVERABLE — the `employees` row itself. AuditObserver snapshots every
 *   auditable attribute into `old_values` on the `deleted` event, and Employee
 *   is on the audited list, so the person's name, staff id, outlet, section,
 *   pay, bank details, particulars and document expiry dates are all there.
 *
 *   NOT RECOVERABLE — everything the database cascade took with it:
 *   attendance, clock punches and their selfies, leave entitlements and
 *   requests, time off, overtime claims, salary revisions, statutory profile,
 *   pay components, payroll adjustments, roster entries, certifications,
 *   login codes and scanned identity documents. Those rows were removed by
 *   MySQL, which raises no Eloquent events, so nothing observed them and
 *   nothing logged them. There is no record of them anywhere in the
 *   application. ONLY A DATABASE BACKUP HAS THEM.
 *
 * The restored row is written with a NEW id when the original is taken, and
 * keeps it when it is free. Reusing the original id is worth doing where
 * possible: the payroll and payslip tables that merely nulled their reference
 * (payroll_run_lines, payslip_deliveries) can then be pointed back by hand.
 */
class RestoreDeletedEmployee extends Command
{
    protected $signature = 'hr:restore-employee
                            {id? : The employee id to restore. Omit to list what is recoverable.}
                            {--company= : Narrow the listing to one company id.}
                            {--force : Restore even when somebody with that staff id is already on file.}';

    protected $description = 'Rebuild a hard-deleted employee from the audit trail';

    public function handle(): int
    {
        $id = $this->argument('id');

        return $id === null ? $this->listDeletions() : $this->restore((int) $id);
    }

    /** Every hard delete still recoverable, newest first. */
    private function listDeletions(): int
    {
        $rows = $this->deletionQuery()->orderByDesc('created_at')->limit(50)->get();

        if ($rows->isEmpty()) {
            $this->info('No deleted employees found in the audit trail.');

            return self::SUCCESS;
        }

        $this->table(
            ['Employee id', 'Name', 'Staff id', 'Company', 'Deleted at', 'Deleted by', 'State'],
            $rows->map(function (AuditLog $log) {
                $v = $log->old_values ?? [];

                return [
                    $log->auditable_id,
                    $v['name'] ?? '(unknown)',
                    $v['staff_id'] ?? '—',
                    $log->company_id,
                    $log->created_at?->format('Y-m-d H:i'),
                    $log->actorName(),
                    $this->stateOf($log),
                ];
            })->all()
        );

        $this->newLine();
        $this->line('Gone      — the row is not in the database. Rebuild it with:');
        $this->line('            php artisan hr:restore-employee <employee id>');
        $this->line('Soft      — the row is still there and this command is not needed.');
        $this->line('            Put it back from the staff list: Status filter → Deleted → Restore.');

        return self::SUCCESS;
    }

    private function restore(int $id): int
    {
        $log = $this->deletionQuery()
            ->where('auditable_id', $id)
            ->orderByDesc('created_at')
            ->first();

        if (! $log) {
            $this->error("No 'deleted' audit entry found for employee #{$id}.");
            $this->line('Run without an id to see what is recoverable.');

            return self::FAILURE;
        }

        $values = $log->old_values ?? [];
        if (empty($values['name'])) {
            $this->error("The audit entry for employee #{$id} carries no attributes to restore from.");

            return self::FAILURE;
        }

        // Only columns that still exist: the snapshot is from whenever the
        // delete happened, and the table has moved on since.
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('employees');
        $attributes = array_intersect_key($values, array_flip($columns));
        unset($attributes['id'], $attributes['deleted_at'], $attributes['deleted_by']);

        $existing = Employee::withoutGlobalScopes()->withTrashed()->find($id);
        if ($existing) {
            $this->warn("Employee #{$id} already exists ({$existing->name}). A new row will be created instead.");
        }

        if (! empty($attributes['staff_id']) && ! $this->option('force')) {
            $clash = Employee::withoutGlobalScopes()->withTrashed()
                ->where('company_id', $attributes['company_id'] ?? $log->company_id)
                ->where('staff_id', $attributes['staff_id'])
                ->first();

            if ($clash) {
                $this->error(sprintf(
                    'Staff id %s is already held by #%d (%s). Pass --force to restore anyway.',
                    $attributes['staff_id'], $clash->id, $clash->name
                ));

                return self::FAILURE;
            }
        }

        $this->line('About to restore:');
        $this->table(['Field', 'Value'], collect($attributes)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v, $k) => [$k, is_scalar($v) ? (string) $v : json_encode($v)])
            ->values()->all());

        $this->newLine();
        $this->warn('This restores the employee RECORD only. Attendance, punches, leave, '
            . 'overtime, salary history, statutory profile, payroll adjustments, roster '
            . 'entries, certifications and uploaded documents were removed by the database '
            . 'cascade and are NOT in the audit trail. Only a database backup has those.');
        $this->newLine();

        if (! $this->confirm('Restore this employee?', true)) {
            $this->line('Nothing was changed.');

            return self::SUCCESS;
        }

        $employee = DB::transaction(function () use ($attributes, $id, $existing, $log) {
            $employee = new Employee();

            // Keep the original id when it is free — payroll_run_lines and
            // payslip_deliveries only NULLED their employee_id, so those rows
            // can be pointed back afterwards if the id matches.
            if (! $existing) {
                $employee->id = $id;
            }

            $employee->forceFill($attributes + [
                'company_id' => $attributes['company_id'] ?? $log->company_id,
            ]);
            $employee->save();

            return $employee;
        });

        $this->newLine();
        $this->info(sprintf('Restored %s as employee #%d.', $employee->name, $employee->id));

        if ($employee->id !== $id) {
            $this->warn("The original id (#{$id}) was taken, so this is a new record. "
                . 'Any payslip or payroll line still pointing at the old id will not reattach.');
        }

        return self::SUCCESS;
    }

    /**
     * Hard deletes only. `force_deleted` is included because that is what a
     * soft-deleting model records when a row is genuinely destroyed.
     *
     * The company scope is dropped: this runs from a terminal with no
     * authenticated user, and refusing to find the row because nobody is
     * logged in is the opposite of useful during a recovery.
     */
    private function deletionQuery()
    {
        $query = AuditLog::withoutGlobalScopes()
            ->where('auditable_type', (new Employee())->getMorphClass())
            ->whereIn('event', ['deleted', 'force_deleted']);

        if ($company = $this->option('company')) {
            $query->where('company_id', (int) $company);
        }

        return $query;
    }

    /**
     * What actually became of this deletion.
     *
     * Since Employee soft-deletes, a `deleted` audit entry no longer means the
     * row is gone — most of them are now recoverable from the staff list, and
     * telling somebody to run a recovery command for one of those would be
     * sending them the long way round.
     */
    private function stateOf(AuditLog $log): string
    {
        $row = Employee::withoutGlobalScopes()->withTrashed()->find($log->auditable_id);

        if (! $row) {
            return 'gone';
        }

        // The id is occupied. Either this delete was a soft one, or the id was
        // later handed to somebody else — the name says which.
        $name = $log->old_values['name'] ?? null;
        if ($name !== null && $row->name !== $name) {
            return 'gone (id reused)';
        }

        return $row->trashed() ? 'soft' : 'on file';
    }
}
