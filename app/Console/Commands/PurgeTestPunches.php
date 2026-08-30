<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\ClockEvent;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Destroy the punches somebody made while TESTING the clock.
 *
 * Every other route out of a clock event is a soft delete, and that is right
 * for all of them: HR › Clock Events removes a punch a manager judged wrong,
 * and the row survives because somebody may need to prove later what was
 * removed and by whom. This command exists for the one case that argument does
 * not cover — punches that were never a claim about a shift at all, made by
 * whoever was checking that the camera worked. There is nothing to audit,
 * nobody was paid, and leaving them soft-deleted means every export, retention
 * job and future migration goes on carrying them.
 *
 * SO IT REALLY DELETES. That is the whole point of it, and it is why almost
 * everything below is a check rather than a deletion:
 *
 *   IT NEVER GUESSES WHO. Employees are matched, listed with their punch
 *   counts and date ranges, and printed for a human to read before anything
 *   happens. A name fragment that matches two people stops the command rather
 *   than picking one — "Mohd" is not a name here, it is a prefix on hundreds.
 *
 *   IT IS A DRY RUN UNLESS TOLD OTHERWISE. --execute is the flag that deletes;
 *   without it the command reports exactly what it would destroy and exits.
 *
 *   IT ASKS, and the confirmation is the punch count typed back, not a y/n.
 *   A yes is muscle memory. A number is read.
 *
 *   IT DELETES ROW BY ROW, not with a mass query. ClockEvent::forceDeleted
 *   removes the selfie from disk, and a `whereIn(...)->forceDelete()` does not
 *   fire model events — it would leave a photograph of somebody's face in
 *   storage with no row pointing at it and no way left to find it.
 *
 * ATTENDANCE IS A SEPARATE DECISION, behind --with-attendance. A clock-in
 * writes an attendance_records row when mark_attendance is on, and nothing
 * links the two: no foreign key, no cascade. So deleting punches alone leaves
 * the ✓ standing on the attendance grid — which is what feeds payroll and the
 * service charge distribution. That is the RIGHT default, because an
 * attendance mark may have been made or corrected by a manager by hand, and
 * this command cannot tell a test punch's ✓ from a real day's. Passing the
 * flag says you know those days were never worked.
 *
 * Payroll is not touched under any flag. If a run has already been calculated
 * over these dates, deleting the days underneath it does not re-open it — go
 * and look at the run.
 */
class PurgeTestPunches extends Command
{
    protected $signature = 'clock:purge-test-punches
                            {employee* : Staff ID or an unambiguous part of the name}
                            {--company= : Company id, required when a name matches across companies}
                            {--from= : Only punches on or after this date (Y-m-d)}
                            {--to= : Only punches on or before this date (Y-m-d)}
                            {--with-attendance : Also delete the attendance days these punches marked present}
                            {--execute : Actually delete. Without this it is a dry run}';

    protected $description = 'Permanently delete the clock-in punches made while testing, for named employees';

    public function handle(): int
    {
        $employees = $this->resolve();

        if ($employees === null) {
            return self::FAILURE;
        }

        $rows    = [];
        $dates   = [];
        $total   = 0;
        $days    = 0;

        foreach ($employees as $employee) {
            $punches = $this->punches($employee);
            $count   = (clone $punches)->count();

            if ($count === 0) {
                $this->line("  {$employee->name} — no punches in range.");
                continue;
            }

            $first = (clone $punches)->min('happened_at');
            $last  = (clone $punches)->max('happened_at');

            /*
             * THE DATES ARE CAPTURED NOW, BEFORE ANYTHING IS DELETED.
             *
             * The attendance days are identified by the work_date of the
             * punches that wrote them, so reading them after the punches are
             * gone finds nothing — which is not a crash, it is --with-attendance
             * silently doing nothing at all. Held per employee and handed to
             * the delete pass below.
             */
            $dates[$employee->id] = (clone $punches)
                ->pluck('work_date')
                ->map(fn ($date) => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date)
                ->unique()
                ->values()
                ->all();

            $attendance = $this->option('with-attendance')
                ? $this->attendance($employee, $dates[$employee->id])->count()
                : 0;

            $rows[] = [
                $employee->id,
                $employee->name,
                $employee->staff_id ?: '—',
                $count,
                substr((string) $first, 0, 16),
                substr((string) $last, 0, 16),
                $this->option('with-attendance') ? $attendance : '—',
            ];

            $total += $count;
            $days  += $attendance;
        }

        if ($total === 0) {
            $this->info('Nothing to delete.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Id', 'Employee', 'Staff ID', 'Punches', 'First', 'Last', 'Attendance days'],
            $rows,
        );

        // Said plainly and every time, including on the dry run. Somebody who
        // reads this only once should read the sentence that matters.
        $this->warn('This is a PERMANENT delete. There is no restore, and the selfies go with it.');

        if ($this->option('with-attendance')) {
            $this->warn(
                "--with-attendance will also remove {$days} attendance day(s). "
                . 'Those feed payroll and the service charge distribution.'
            );
        } else {
            $this->line(
                'Attendance days are NOT being touched. Any ✓ these punches marked stays on the grid.'
            );
        }

        if (! $this->option('execute')) {
            $this->newLine();
            $this->info("Dry run — nothing was deleted. Add --execute to delete {$total} punch(es).");

            return self::SUCCESS;
        }

        // The count typed back, not a yes. A yes is muscle memory; a number
        // means the table above was actually read.
        $typed = (string) $this->ask("Type the number of punches to delete ({$total}) to confirm");

        if ($typed !== (string) $total) {
            $this->error('Not confirmed. Nothing was deleted.');

            return self::FAILURE;
        }

        $deleted    = 0;
        $daysGone   = 0;

        foreach ($employees as $employee) {
            /*
             * Chunked and force-deleted ONE MODEL AT A TIME, so
             * ClockEvent::forceDeleted fires and takes the selfie off disk
             * with it. A mass forceDelete() would be faster and would leave
             * photographs of people's faces in storage with nothing pointing
             * at them.
             *
             * chunkById, not chunk: the query filters on the very rows being
             * removed, so a paged offset would skip half of them.
             */
            $this->punches($employee)->chunkById(200, function ($events) use (&$deleted) {
                foreach ($events as $event) {
                    $event->forceDelete();
                    $deleted++;
                }
            });

            if ($this->option('with-attendance')) {
                $daysGone += DB::transaction(
                    fn () => $this->attendance($employee, $dates[$employee->id] ?? [])->delete()
                );
            }
        }

        $this->info("Deleted {$deleted} punch(es)."
            . ($this->option('with-attendance') ? " Deleted {$daysGone} attendance day(s)." : ''));

        return self::SUCCESS;
    }

    /**
     * The named employees, or null when any argument is not exactly one person.
     *
     * @return \Illuminate\Support\Collection<int, Employee>|null
     */
    private function resolve(): ?\Illuminate\Support\Collection
    {
        $company = $this->option('company') ? (int) $this->option('company') : null;
        $found   = collect();

        foreach ($this->argument('employee') as $term) {
            $term = trim((string) $term);

            $matches = Employee::withoutGlobalScopes()
                ->when($company, fn ($q) => $q->where('company_id', $company))
                ->where(fn ($q) => $q
                    ->where('staff_id', $term)
                    ->orWhere('name', 'like', '%' . $term . '%'))
                ->orderBy('name')
                ->get();

            if ($matches->isEmpty()) {
                $this->error("No employee matches \"{$term}\".");

                return null;
            }

            /*
             * AMBIGUITY IS A HARD STOP, never a "did you mean".
             *
             * Names here are not distinctive at the front — "Mohd", "Muhammad"
             * and "Nur" prefix a large share of the workforce — and this
             * command destroys attendance history. Picking the best match
             * would eventually pick the wrong person, once, silently.
             */
            if ($matches->count() > 1) {
                $this->error("\"{$term}\" matches " . $matches->count() . ' employees:');

                foreach ($matches as $match) {
                    $this->line("  #{$match->id}  {$match->name}  (staff id: " . ($match->staff_id ?: '—')
                        . ', company ' . $match->company_id . ')');
                }

                $this->line('Narrow it with a staff ID, a fuller name, or --company.');

                return null;
            }

            $found->push($matches->first());
        }

        return $found->unique('id')->values();
    }

    /** This employee's punches, within whatever window was asked for. */
    private function punches(Employee $employee)
    {
        return ClockEvent::withoutGlobalScopes()
            // withTrashed in effect: punches already soft-deleted from the HR
            // screen are still rows, and "delete the test data" means them too.
            ->withTrashed()
            ->where('employee_id', $employee->id)
            ->when($this->option('from'), fn ($q) => $q->whereDate('work_date', '>=', $this->option('from')))
            ->when($this->option('to'), fn ($q) => $q->whereDate('work_date', '<=', $this->option('to')));
    }

    /**
     * The attendance days those punches marked.
     *
     * @param  array<int, string>  $dates  Y-m-d, captured BEFORE the punches
     *         were deleted — see handle(). An empty list matches nothing,
     *         which is the correct answer for an employee with no punches.
     */
    private function attendance(Employee $employee, array $dates)
    {
        if ($dates === []) {
            return AttendanceRecord::withoutGlobalScopes()->whereRaw('1 = 0');
        }

        return AttendanceRecord::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            /*
             * ONLY days a punch could have written, and only where a punch
             * actually exists.
             *
             * The alternative — deleting every attendance row in the window —
             * would take out days a manager typed by hand on the grid, which
             * are not test data and are not this command's to remove. An
             * hours-based row is likewise left alone: it was entered by
             * somebody, never by markPresent().
             */
            ->whereNull('hours')
            /*
             * orWhereDate per day, not whereIn.
             *
             * work_date is a DATE column in MySQL but SQLite keeps it as
             * "2026-08-20 00:00:00", so a whereIn against "2026-08-20" matches
             * nothing there — and the tests run on SQLite, which is exactly
             * how a purge that silently deleted no attendance at all passed
             * for a while. whereDate is translated per driver and means the
             * same thing on both.
             */
            ->where(function ($q) use ($dates) {
                foreach ($dates as $date) {
                    $q->orWhereDate('work_date', $date);
                }
            });
    }
}
