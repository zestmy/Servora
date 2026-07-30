<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotate output 90° for printers whose driver only offers portrait media.
 *
 * A 70 × 40 label is landscape, but some label drivers define the media as
 * 40 wide × 70 tall and refuse anything else. Sending a 70mm-wide page to a
 * 40mm-wide medium makes the browser paginate, and the run eats several
 * labels to print one.
 *
 * With this on, the page is emitted at the swapped size and the label
 * content is turned 90° to fit it. The label itself is unchanged — rotation
 * is a property of the PRINTER, not of the template, so the designer and
 * the archive PDF both stay in natural orientation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_printers', function (Blueprint $table) {
            $table->boolean('rotate_90')->default(false)->after('offset_y_mm');
        });
    }

    public function down(): void
    {
        Schema::table('label_printers', function (Blueprint $table) {
            $table->dropColumn('rotate_90');
        });
    }
};
