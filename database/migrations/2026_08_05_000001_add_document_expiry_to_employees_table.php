<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expiry dates for the two compliance documents that had none.
 *
 * Typhoid already carried valid_from + expired_on; food handler and halal
 * training were "yes/no plus a date it happened", which cannot answer the
 * question the compliance card and the reminder email exist to answer —
 * who lapses next. Both are explicit dates rather than a company-wide
 * renewal interval applied to the attended date, because a certificate
 * carries its own printed expiry and guessing it would put wrong dates in
 * front of an auditor.
 *
 * Nullable throughout: an employee whose certificate has no recorded expiry
 * is "certified, expiry unknown", which is a real state and must not be
 * confused with expired.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('food_handler_expired_on')->nullable()->after('food_handler_cert_no');
            $table->date('halal_training_expired_on')->nullable()->after('halal_training_date');
        });
    }

    public function down(): void
    {
        // Only drop what is actually there, so a rollback can finish from a
        // half-reverted state rather than dying on the first missing column.
        $columns = array_values(array_filter(
            ['food_handler_expired_on', 'halal_training_expired_on'],
            fn ($c) => Schema::hasColumn('employees', $c)
        ));

        if ($columns) {
            Schema::table('employees', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
