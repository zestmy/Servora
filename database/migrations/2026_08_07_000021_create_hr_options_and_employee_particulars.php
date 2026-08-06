<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee particulars — sex, nationality, race, religion, marital status,
 * education — and the pick-lists behind them.
 *
 * ONE options table for every list rather than six near-identical ones. They
 * differ only in what they are called, and a seventh list should cost a row in
 * HrOption::TYPES plus a nullable column here, not another table and screen.
 *
 * The employee stores the VALUE, not a key, matching `bank_name`. These
 * particulars are printed on the EA form, HRD Corp claims and statutory
 * submissions, and a document issued last year has to keep saying what it said
 * — a foreign key would rewrite history the moment somebody edited the list.
 * The settings screen carries employees across a rename, which is the one case
 * where the two could otherwise drift apart.
 *
 * All nullable, like every other particular: an incomplete record must never
 * block payroll, and these fields will be blank on every employee already on
 * the system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Which list this value belongs to — see HrOption::TYPES.
            $table->string('type', 40);
            $table->string('name', 60);

            // Optional short code for exports that want one (HRD Corp and some
            // statutory returns code race and education rather than name them).
            $table->string('code', 20)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The name IS the stored value on an employee, so a duplicate
            // within a list would make the picked value ambiguous.
            $table->unique(['company_id', 'type', 'name'], 'hr_options_unique');
            $table->index(['company_id', 'type', 'is_active'], 'hr_options_lookup');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('gender', 60)->nullable()->after('date_of_birth');
            $table->string('nationality', 60)->nullable()->after('gender');
            $table->string('race', 60)->nullable()->after('nationality');
            $table->string('religion', 60)->nullable()->after('race');
            $table->string('marital_status', 60)->nullable()->after('religion');
            $table->string('education_level', 60)->nullable()->after('marital_status');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'gender', 'nationality', 'race', 'religion',
                'marital_status', 'education_level',
            ]);
        });

        Schema::dropIfExists('hr_options');
    }
};
