<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pairs of products a user has confirmed are NOT duplicates of each other.
     * The duplicate scanner skips these so dismissed/separated pairs never
     * resurface. Ids are stored low-high so each unordered pair is unique.
     */
    public function up(): void
    {
        Schema::create('ignored_duplicate_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id_a')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('ingredient_id_b')->constrained('ingredients')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'ingredient_id_a', 'ingredient_id_b'], 'idp_company_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ignored_duplicate_pairs');
    }
};
