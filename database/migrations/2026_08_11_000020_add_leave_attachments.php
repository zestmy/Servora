<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A medical certificate attached to the leave it justifies.
 *
 * WHICH TYPES OFFER IT IS DATA, not a hardcoded "if the code is MC". Leave
 * types are per-company and editable by design — "adding marriage leave should
 * not need a deploy" — so a company that wants a document against
 * compassionate leave, or that calls its sick leave something else entirely,
 * ticks a box rather than waiting for us.
 *
 * Backfilled onto the types that plainly mean it today: the seeded MC and HL
 * codes, and anything a company has named "medical", "sick" or
 * "hospitalisation". Anything missed is one tick in Settings > Leave Types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('requires_attachment')->default(false)->after('allows_half_day');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            // Private disk path. Nullable because the upload is OPTIONAL: a
            // clinic that gives a paper slip on Friday should not stop somebody
            // recording Friday's absence.
            $table->string('attachment_path')->nullable()->after('reason');
            // What the employee called it, for the download filename. A stored
            // path is a hash and hands back a file nobody can identify.
            $table->string('attachment_name')->nullable()->after('attachment_path');
        });

        DB::table('leave_types')
            ->where(function ($q) {
                $q->whereIn(DB::raw('UPPER(code)'), ['MC', 'HL', 'SL'])
                    ->orWhere('name', 'like', '%medical%')
                    ->orWhere('name', 'like', '%sick%')
                    ->orWhere('name', 'like', '%hospitalis%')
                    ->orWhere('name', 'like', '%hospitaliz%');
            })
            ->update(['requires_attachment' => true]);
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('requires_attachment');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });
    }
};
