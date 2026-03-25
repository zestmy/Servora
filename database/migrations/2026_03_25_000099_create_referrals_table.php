<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referred_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('status')->default('signed_up'); // signed_up, converted, paid
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->unique(['referral_code_id', 'referred_company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
