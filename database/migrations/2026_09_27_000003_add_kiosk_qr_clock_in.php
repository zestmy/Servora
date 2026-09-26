<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phones clock in by scanning a QR the outlet kiosk shows, and it rotates.
 *
 * clock_settings
 *   qr_mode            off | allowed | required — whether a phone punch may,
 *                      or must, scan the kiosk's QR. See KioskQrPolicy.
 *   qr_rotate_seconds  how often the kiosk draws a new code. Short enough that
 *                      a photo of it sent to somebody still at home is stale by
 *                      the time they open it.
 *
 * clock_events.source gains `qr`: a punch from somebody's own phone that proved
 * where it was by scanning a kiosk, rather than by GPS. clock_device_id then
 * names the kiosk whose code was scanned.
 *
 * change() rather than a raw MODIFY COLUMN so the SQLite test database still
 * builds — see the request-line source migration for the same reasoning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clock_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('clock_settings', 'qr_mode')) {
                $table->string('qr_mode', 10)->default('off');
            }
            if (! Schema::hasColumn('clock_settings', 'qr_rotate_seconds')) {
                $table->unsignedSmallInteger('qr_rotate_seconds')->default(30);
            }
        });

        Schema::table('clock_events', function (Blueprint $table) {
            $table->enum('source', ['kiosk', 'byod', 'manual', 'qr'])
                ->default('byod')->change();
        });
    }

    public function down(): void
    {
        Schema::table('clock_events', function (Blueprint $table) {
            $table->enum('source', ['kiosk', 'byod', 'manual'])
                ->default('byod')->change();
        });

        Schema::table('clock_settings', function (Blueprint $table) {
            $table->dropColumn(['qr_mode', 'qr_rotate_seconds']);
        });
    }
};
