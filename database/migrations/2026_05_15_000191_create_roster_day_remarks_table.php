<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_day_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->constrained()->cascadeOnDelete();
            $table->date('day_date');
            $table->enum('remark_type', ['public_holiday', 'stocktake', 'event', 'custom'])->default('custom');
            $table->string('remark_text');
            $table->timestamps();

            $table->index(['roster_id', 'day_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_day_remarks');
    }
};
