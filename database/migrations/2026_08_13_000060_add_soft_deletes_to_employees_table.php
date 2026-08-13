<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An employee can be deleted from the staff list — and come back.
 *
 * WRITTEN AFTER SOMEBODY DID IT BY ACCIDENT, on a phone, from the staff
 * list, where the delete is a 36px icon in a row of icons and the only
 * guard was a native confirm() whose OK button lands under the thumb that
 * just tapped delete.
 *
 * WHAT THAT COST IS THE REASON THIS IS NOT MERELY A NICER DIALOG. Fifteen
 * tables hang off `employees` with `cascadeOnDelete()`:
 *
 *     attendance_records, clock_events, employee_face_descriptors,
 *     employee_certifications, employee_documents, employee_login_codes,
 *     employee_statutory_profiles, employee_pay_components, salary_revisions,
 *     leave_entitlements, leave_requests, time_off_requests,
 *     overtime_claims, payroll_run_adjustments, roster_entries
 *
 * — and two more (payroll_run_lines, payslip_deliveries) that null their
 * reference, which quietly detaches historic payslips from the person they
 * were paid to.
 *
 * The cascade runs in MySQL, which raises no Eloquent events, so ONE tap
 * destroyed that person's entire attendance history, every clock-in selfie,
 * their leave balances, their salary history, their statutory profile and
 * their scanned identity documents, with nothing written to the audit log
 * for any of it — the audit trail records the `employees` row and cannot
 * see what the database removed underneath it. There is no undo for that
 * and there never could have been; the only recovery is a database backup.
 *
 * A soft delete does not fire the cascade AT ALL. That is the entire point
 * of this migration: the row stays, so the fifteen tables stay, and
 * "remove this person from the list" stops meaning "destroy six years of
 * their records". It follows clock_events, which reached the same
 * conclusion for the same reason a week earlier — see
 * 2026_08_04_000018_add_soft_deletes_to_clock_events_table.
 *
 * `deleted_by` is here for the same reason it is on clock_events: who
 * removed somebody is a different question from who last edited them, and
 * on a shared tablet in a kitchen it is the question actually asked
 * afterwards. It is cleared on restore rather than kept — a live record
 * carrying "deleted by Aisha" reads as a record that is still deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // dropConstrainedForeignId drops the foreign key and then the
            // column, in the order both MySQL and sqlite require.
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });
    }
};
