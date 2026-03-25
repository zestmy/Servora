<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('is_active');
            $table->string('registered_via')->default('seeder')->after('onboarding_completed_at'); // seeder, admin, self_signup
            $table->timestamp('trial_ends_at')->nullable()->after('registered_via');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['onboarding_completed_at', 'registered_via', 'trial_ends_at']);
        });
    }
};
