<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public holidays a company RECOGNISES, and what a replacement day off
 * for one is worth.
 *
 * This is deliberately not `calendar_events` with category "holiday". That
 * calendar is a SALES forecasting input — it exists so AI Analytics knows why
 * a Tuesday took twice the usual money, and it is edited and deleted with that
 * in mind. Deriving somebody's leave entitlement from a row that a marketing
 * user may reword or bin is how entitlements quietly disappear. The register
 * here is HR's own, and imports from that calendar rather than borrowing it.
 *
 * THE ENTITLEMENT IS NOT STORED PER EMPLOYEE.
 *
 * Every active employee earns one replacement day per replaceable holiday that
 * applies to their outlet, so a credit is a fact about the HOLIDAY, not about
 * the person — deriving it costs one query and cannot drift, whereas a table
 * of employee×holiday rows needs backfilling every time somebody joins, moves
 * outlet or has their join date corrected. What is stored is the SPEND:
 * `leave_requests.public_holiday_id` says which holiday a day off replaced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->date('date');
            $table->string('name');                        // Hari Raya Aidilfitri

            /*
             * Whether working this holiday earns a day off in lieu.
             *
             * False = the company PAYS FOR IT INSTEAD, at the rate the
             * Employment Act sets for work on a public holiday. The holiday
             * still belongs in the register — it prints on the roster and the
             * attendance sheet, and staff need to be able to see that the day
             * was settled in the pay run rather than lost. It simply issues no
             * credit, and the apply form says why.
             */
            $table->boolean('is_replaceable')->default(true);

            /*
             * How long the replacement stays claimable, OVERRIDING the company
             * default in `leave_settings`. Null = follow the default, which is
             * what almost every row will do; a per-holiday override exists
             * because the year-end holidays are the ones that need a longer
             * rope than the company's usual window.
             *
             *   days      → claimable until `date` + expiry_days
             *   year_end  → claimable until 31 December of the holiday's year
             *   never     → no deadline
             */
            $table->string('expiry_mode', 20)->nullable();
            $table->unsignedSmallInteger('expiry_days')->nullable();

            $table->string('notes', 255)->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One entry per holiday per day. Malaysia observes two distinct
            // holidays on one date often enough (a state holiday landing on a
            // federal one) that the date alone cannot be the key.
            $table->unique(['company_id', 'date', 'name'], 'public_holidays_unique');
            $table->index(['company_id', 'date']);
        });

        /*
         * Which outlets observe the holiday. NO ROWS MEANS EVERY OUTLET.
         *
         * That default is the whole point: federal holidays are the common
         * case and should need no clicking, while Thaipusam is Selangor's and
         * not Johor's. Storing the exception rather than the rule also means a
         * new outlet automatically observes every federal holiday already in
         * the register instead of silently observing none.
         */
        Schema::create('public_holiday_outlet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_holiday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();

            $table->unique(['public_holiday_id', 'outlet_id'], 'public_holiday_outlet_unique');
        });

        Schema::create('leave_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete()->unique();

            // The default claim window for a replacement public holiday, used
            // by every holiday that does not override it. `days` with 90 is
            // the common Malaysian house rule — take it within the quarter.
            $table->string('rph_expiry_mode', 20)->default('days');   // days|year_end|never
            $table->unsignedSmallInteger('rph_expiry_days')->default(90);

            $table->timestamps();
        });

        Schema::table('leave_types', function (Blueprint $table) {
            /*
             * Marks the ONE type whose balance comes from the holiday register
             * rather than a flat yearly figure.
             *
             * A flag on the type rather than a pointer in settings, because
             * everything that has to know — the balance service, both apply
             * forms, the settings list — already has the type in hand and
             * would otherwise need a second lookup to find out what it is.
             */
            $table->boolean('is_replacement_holiday')->default(false)->after('is_claimable');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            /*
             * Which holiday this day off replaces.
             *
             * Nullable because every other kind of leave draws on a yearly
             * balance and has nothing to point at. For a replacement it is
             * what makes the credit SPENT: without it, "two days left" could
             * only ever be a count, and nobody could answer "which holiday did
             * I still not take?" — the question staff actually ask.
             *
             * restrictOnDelete: a holiday with leave taken against it must be
             * deactivated, not deleted, or an approved day off loses the only
             * record of what it was for.
             */
            $table->foreignId('public_holiday_id')->nullable()->after('leave_type_id')
                ->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['public_holiday_id']);
            $table->dropColumn('public_holiday_id');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('is_replacement_holiday');
        });

        Schema::dropIfExists('leave_settings');
        Schema::dropIfExists('public_holiday_outlet');
        Schema::dropIfExists('public_holidays');
    }
};
