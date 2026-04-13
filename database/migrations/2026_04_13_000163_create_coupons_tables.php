<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('description')->nullable();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            // Grant type: days = add N days; months = add N months; lifetime = very long period
            $table->enum('grant_type', ['days', 'months', 'lifetime']);
            $table->unsignedInteger('grant_value')->nullable(); // null for lifetime
            $table->unsignedInteger('max_redemptions')->nullable(); // null = unlimited
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->timestamp('expires_at')->nullable(); // coupon self-expiry
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->unique(['coupon_id', 'company_id']); // one redemption per company per coupon
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
