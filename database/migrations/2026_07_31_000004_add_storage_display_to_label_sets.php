<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-set control over the storage temperature printed on its QR label.
 *
 * Deriving it from the set's lines is a good default but a poor rule: a
 * chiller door is a chiller door even when the set happens to hold one
 * frozen item, and the label on that door should say 0-4°C rather than
 * listing both and leaving staff to work out which applies to the unit
 * they are standing in front of.
 *
 * storage_states null means "work it out from the items", which is what
 * every existing set keeps doing. An explicit array overrides that.
 * show_storage hides the block entirely for sets where a temperature makes
 * no sense — a takeaway station, say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_sets', function (Blueprint $table) {
            $table->boolean('show_storage')->default(true)->after('description');
            $table->json('storage_states')->nullable()->after('show_storage');
        });
    }

    public function down(): void
    {
        Schema::table('label_sets', function (Blueprint $table) {
            $table->dropColumn(['show_storage', 'storage_states']);
        });
    }
};
