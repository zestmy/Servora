<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analysis_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 7);
            $table->string('analysis_type', 50);
            $table->string('prompt_hash', 64);
            $table->longText('prompt_text');
            $table->longText('response_text');
            $table->string('model_used', 100);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'period', 'analysis_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analysis_logs');
    }
};
