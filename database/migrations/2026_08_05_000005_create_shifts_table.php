<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Named shift templates, so building a duty roster stops being a week of
 * typing the same four times into every cell.
 *
 * A shift is a template, not a booking: applying one COPIES its times onto the
 * roster entry and records which shift it came from. Editing the template
 * later must not silently rewrite rosters that were already approved, which is
 * why the entry keeps its own copy of the times rather than reading through.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Both nullable = available everywhere. A section-specific shift is
            // the common case (BOH starts before FOH), an outlet-specific one
            // covers the branch that opens late.
            $table->foreignId('outlet_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 60);
            // Short label for the roster grid, where a full name will not fit.
            $table->string('code', 12)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('rest_duration')->default(60);
            // Null = fall back to the outlet's roster setting, same as an entry.
            $table->decimal('normal_hours', 5, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'sort_order']);
            $table->index(['outlet_id', 'section_id']);
        });

        Schema::table('roster_entries', function (Blueprint $table) {
            // nullOnDelete, not cascade: deleting a shift template must never
            // take roster history with it.
            $table->foreignId('shift_id')->nullable()->after('station_id')
                ->constrained('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('roster_entries', 'shift_id')) {
            Schema::table('roster_entries', function (Blueprint $table) {
                $table->dropConstrainedForeignId('shift_id');
            });
        }

        Schema::dropIfExists('shifts');
    }
};
