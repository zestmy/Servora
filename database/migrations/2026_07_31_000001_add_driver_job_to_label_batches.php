<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record which transport a print run used, and its remote job reference.
 *
 * The job id belongs on the BATCH, not on each label: one batch is one
 * document is one PrintNode job. Putting it per-line would imply a
 * granularity that doesn't exist.
 *
 * Worth having even though nothing reconciles job state yet. Without the id
 * there is no way to answer "the chef says it never came out" — with it,
 * the job can be looked up in PrintNode's own console.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_print_batches', function (Blueprint $table) {
            $table->string('driver', 20)->nullable()->after('printed_at');
            $table->string('driver_job_id')->nullable()->after('driver');
        });

        // Everything printed so far went through the browser. Leaving these
        // null would misrepresent history as "transport unknown".
        DB::table('label_print_batches')->whereNull('driver')->update(['driver' => 'browser']);
    }

    public function down(): void
    {
        Schema::table('label_print_batches', function (Blueprint $table) {
            $table->dropColumn(['driver', 'driver_job_id']);
        });
    }
};
