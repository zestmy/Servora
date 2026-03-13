<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('receiver_name', 100)->nullable()->after('notes');
            $table->foreignId('department_id')->nullable()->after('receiver_name')
                  ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('receiver_name');
        });

        Schema::dropIfExists('departments');
    }
};
