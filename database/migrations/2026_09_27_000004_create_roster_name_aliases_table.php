<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The names an outlet's Excel roster uses for its people.
 *
 * The sheet says "FARHAN"; the employee record says "Muhammad Farhan bin
 * Ahmad". Once a manager has matched the two on the PDF import screen, the
 * next week's upload should not ask again — this is where that answer is
 * kept. Per outlet, because two outlets can each have their own "SITI".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_name_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 100);
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['outlet_id', 'alias']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_name_aliases');
    }
};
