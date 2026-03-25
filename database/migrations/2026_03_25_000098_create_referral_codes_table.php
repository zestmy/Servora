<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->string('referrer_type'); // user, affiliate
            $table->unsignedBigInteger('referrer_id');
            $table->string('code')->unique();
            $table->string('url');
            $table->unsignedInteger('total_clicks')->default(0);
            $table->unsignedInteger('total_signups')->default(0);
            $table->unsignedInteger('total_conversions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['referrer_type', 'referrer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
