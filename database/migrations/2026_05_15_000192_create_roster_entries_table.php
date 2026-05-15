<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('roster_stations')->nullOnDelete();
            $table->date('day_date');
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->integer('rest_duration')->default(60); // in minutes
            $table->decimal('hours_worked', 5, 2)->default(0);
            $table->decimal('planned_ot', 5, 2)->default(0);
            $table->boolean('planned_ot_manual')->default(false);
            $table->boolean('is_off_day')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['roster_id', 'employee_id']);
            $table->index(['roster_id', 'day_date']);
            $table->unique(['roster_id', 'employee_id', 'day_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_entries');
    }
};
