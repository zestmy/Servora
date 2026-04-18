<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turn `ot_employees` (built for overtime claims only) into a general
 * `employees` master table that the coming duty roster module and CSV
 * imports can also use. Adds the columns the user's CSV carries.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the FK from overtime_claims first so the table rename is allowed.
        $fkExists = DB::select("
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'overtime_claims'
              AND CONSTRAINT_NAME = 'overtime_claims_employee_id_foreign'
            LIMIT 1
        ");
        if ($fkExists) {
            Schema::table('overtime_claims', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });
        }

        if (Schema::hasTable('ot_employees') && ! Schema::hasTable('employees')) {
            Schema::rename('ot_employees', 'employees');
        }

        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'position') && ! Schema::hasColumn('employees', 'designation')) {
                $table->renameColumn('position', 'designation');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'staff_id')) $table->string('staff_id')->nullable()->after('name');
            if (! Schema::hasColumn('employees', 'department')) $table->string('department')->nullable()->after('designation');
            if (! Schema::hasColumn('employees', 'email')) $table->string('email')->nullable()->after('department');
            if (! Schema::hasColumn('employees', 'phone')) $table->string('phone', 50)->nullable()->after('email');
        });

        // Helpful indexes for lookups and de-duping during CSV upsert.
        Schema::table('employees', function (Blueprint $table) {
            try { $table->index(['company_id', 'staff_id'], 'employees_company_staff_idx'); } catch (\Throwable $e) {}
            try { $table->index(['company_id', 'email'], 'employees_company_email_idx'); } catch (\Throwable $e) {}
        });

        // Recreate the FK against the renamed table.
        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            try { $table->dropIndex('employees_company_staff_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex('employees_company_email_idx'); } catch (\Throwable $e) {}
            if (Schema::hasColumn('employees', 'phone'))      $table->dropColumn('phone');
            if (Schema::hasColumn('employees', 'email'))      $table->dropColumn('email');
            if (Schema::hasColumn('employees', 'department')) $table->dropColumn('department');
            if (Schema::hasColumn('employees', 'staff_id'))   $table->dropColumn('staff_id');
            if (Schema::hasColumn('employees', 'designation')) $table->renameColumn('designation', 'position');
        });

        if (Schema::hasTable('employees') && ! Schema::hasTable('ot_employees')) {
            Schema::rename('employees', 'ot_employees');
        }

        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('ot_employees')->cascadeOnDelete();
        });
    }
};
