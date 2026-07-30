<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-printer print offsets, in millimetres.
 *
 * Every thermal printer has an unprintable margin, and the value is not
 * something you can look up reliably — it shifts with the media, the driver
 * and how the roll is seated. Rather than encode a guess per model, the
 * calibration label measures it: the operator prints a ruler, reads off how
 * much is missing, and enters it here.
 *
 * The renderer then shifts every field by the offset, so a printer that
 * swallows 2mm on the left simply has its content moved 2mm right instead
 * of clipping the first character of every line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_printers', function (Blueprint $table) {
            // Signed: a printer can clip on either side, and correcting for
            // an over-eager top margin means moving content up.
            $table->decimal('offset_x_mm', 5, 2)->default(0)->after('height_mm');
            $table->decimal('offset_y_mm', 5, 2)->default(0)->after('offset_x_mm');
        });
    }

    public function down(): void
    {
        Schema::table('label_printers', function (Blueprint $table) {
            $table->dropColumn(['offset_x_mm', 'offset_y_mm']);
        });
    }
};
