<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of the three built-in documents actually expire, per company.
 *
 * The catalogue courses have carried a has_expiry flag from the start; the
 * built-ins were assumed to expire, which is wrong for most kitchens — a food
 * handler certificate and halal awareness training are typically attended once
 * and never renewed, while the typhoid jab is the one that lapses. Assuming
 * otherwise filled the compliance card with rows saying "no expiry date" for
 * documents that have nothing to record.
 *
 * Defaults match that reality: typhoid expires, the other two do not. A company
 * that does renew them ticks the box.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('typhoid_expires')->default(true);
            $table->boolean('food_handler_expires')->default(false);
            $table->boolean('halal_training_expires')->default(false);
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_settings');
    }
};
