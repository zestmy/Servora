<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Break punches: type gains 'break_start' and 'break_end'.
     *
     * The column becomes a plain string rather than a wider enum. An enum
     * costs an ALTER — and on MySQL that is written as `MODIFY COLUMN`, which
     * is the exact construct that already stops this repo's migrations from
     * running on sqlite and blocks its test suite. A varchar with the values
     * owned by ClockEvent's constants adds no further platform lock-in, and
     * the next punch type needs no migration at all.
     */
    public function up(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            $table->string('type', 20)->default('in')->change();
        });
    }

    public function down(): void
    {
        // Any break rows would not fit the old two-value enum, so they go
        // first — down() has to leave a table the old column can hold.
        \Illuminate\Support\Facades\DB::table('clock_events')
            ->whereIn('type', ['break_start', 'break_end'])
            ->delete();

        Schema::table('clock_events', function (Blueprint $table) {
            $table->enum('type', ['in', 'out'])->change();
        });
    }
};
