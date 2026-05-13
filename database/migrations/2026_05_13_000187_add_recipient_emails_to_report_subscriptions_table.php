<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_subscriptions', function (Blueprint $table) {
            $table->json('recipient_emails')->nullable()->after('include_ai_insights');
        });
    }

    public function down(): void
    {
        Schema::table('report_subscriptions', function (Blueprint $table) {
            $table->dropColumn('recipient_emails');
        });
    }
};
