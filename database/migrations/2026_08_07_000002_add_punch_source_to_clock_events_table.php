<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a punch arrived: the outlet kiosk, somebody's own phone, or a manager
 * typing it in.
 *
 * A COLUMN, not a flag. The flags array is for anomalies — the service
 * deliberately keeps `late` and `no_shift` out of the review queue on the
 * grounds that they are records of what happened rather than problems to
 * solve — and the source of a punch is the same kind of fact. It is also what
 * the review queue filters on and what the by-source report groups by, and
 * neither wants to be searching a JSON column to do it.
 *
 * Existing rows are byod, which is what they all are: until this migration
 * the only way to punch was the staff app on somebody's own device.
 *
 * `manual` has no writer yet. It is declared now because the alternative is
 * an enum migration later on a table that will by then be large, and because
 * a report that groups by source should not have to explain a fourth category
 * appearing halfway down the year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            $table->enum('source', ['kiosk', 'byod', 'manual'])
                ->default('byod')
                ->after('type');

            // Which kiosk, when it was one. Null for every BYOD punch, and
            // null again if the device row is ever hard-deleted — the punch
            // still carries source=kiosk, so the fact survives the pointer.
            $table->foreignId('clock_device_id')
                ->nullable()
                ->after('source')
                ->constrained('clock_devices')
                ->nullOnDelete();

            // The by-source report, and the "is this person punching on their
            // phone at a kiosk outlet" check that is the whole point of it.
            $table->index(['company_id', 'source', 'work_date'], 'clock_events_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            $table->dropIndex('clock_events_source_index');
            $table->dropForeign(['clock_device_id']);
            $table->dropColumn(['source', 'clock_device_id']);
        });
    }
};
