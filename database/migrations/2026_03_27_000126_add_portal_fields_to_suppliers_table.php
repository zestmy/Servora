<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('whatsapp_number', 20)->nullable()->after('phone');
            $table->enum('notification_preference', ['email', 'whatsapp', 'both'])->default('email')->after('whatsapp_number');
            $table->boolean('portal_enabled')->default(false)->after('notification_preference');
            $table->string('tax_registration_number', 50)->nullable()->after('portal_enabled');
            $table->text('billing_address')->nullable()->after('tax_registration_number');
            $table->string('bank_name', 100)->nullable()->after('billing_address');
            $table->string('bank_account_number', 50)->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_number', 'notification_preference', 'portal_enabled',
                'tax_registration_number', 'billing_address', 'bank_name', 'bank_account_number',
            ]);
        });
    }
};
