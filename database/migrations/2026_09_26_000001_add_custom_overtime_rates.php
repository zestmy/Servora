<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Custom OT types with a FIXED hourly rate — "Part Time RM15/hour" — beside
 * the three statutory ones, which are the employee's hourly rate × a
 * multiplier.
 *
 * A custom claim's ot_type is "custom_{id}", so everything that already
 * groups or keys by ot_type (payroll's per-type OT, the PDFs, the trend
 * chart) separates each custom type on its own without a second column to
 * group by.
 *
 * ot_hourly_rate is the rate COPIED ONTO THE CLAIM when it is saved. A rate
 * changed later reprices new claims, not ones already made — the same reason
 * payroll run lines snapshot everything they print.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_rate_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->decimal('hourly_rate', 10, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        // Was an ENUM of the three statutory types, which a custom type
        // cannot be stored in. MySQL only: SQLite has no enum, and Laravel's
        // enum there is already a plain string with a CHECK the change()
        // below rebuilds.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE overtime_claims MODIFY ot_type VARCHAR(30) NOT NULL");
        } else {
            Schema::table('overtime_claims', function (Blueprint $table) {
                $table->string('ot_type', 30)->change();
            });
        }

        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->decimal('ot_hourly_rate', 10, 2)->nullable()->after('ot_type');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->dropColumn('ot_hourly_rate');
        });

        // Custom claims have no statutory type to go back to; they are
        // folded into Normal Day rather than making the enum unrestorable.
        DB::table('overtime_claims')->where('ot_type', 'like', 'custom_%')->update(['ot_type' => 'normal_day']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE overtime_claims MODIFY ot_type ENUM('normal_day','public_holiday','rest_day') NOT NULL");
        }

        Schema::dropIfExists('overtime_rate_types');
    }
};
