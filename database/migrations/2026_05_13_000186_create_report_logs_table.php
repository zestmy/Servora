<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained('report_subscriptions')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type', 50);
            $table->date('report_date'); // The date the report covers
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('delivery_channel', 20);
            $table->string('recipient_email')->nullable();
            $table->string('delivery_status', 20)->default('pending'); // pending, sent, failed, opened
            $table->json('report_data')->nullable(); // Cached metrics used in report
            $table->json('ai_insights')->nullable(); // AI-generated insights
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'report_date']);
            $table->index(['delivery_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_logs');
    }
};
