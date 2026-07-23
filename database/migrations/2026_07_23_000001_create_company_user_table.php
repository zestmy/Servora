<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        // Backfill: every user's current company becomes their first membership.
        DB::statement(
            'INSERT INTO company_user (company_id, user_id, created_at, updated_at)
             SELECT company_id, id, NOW(), NOW() FROM users WHERE company_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
