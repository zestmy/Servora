<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_type', 50); // daily_sales, weekly_performance, monthly_summary
            $table->string('frequency', 20); // daily, weekly, monthly
            $table->string('delivery_channel', 20)->default('email'); // email, slack, whatsapp
            $table->time('delivery_time')->default('06:00:00');
            $table->tinyInteger('delivery_day')->nullable(); // 1-7 for weekly (Monday=1), 1-28 for monthly
            $table->boolean('is_active')->default(true);
            $table->boolean('include_ai_insights')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->index(['frequency', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_subscriptions');
    }
};
