<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_templates', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('is_active')->constrained('suppliers')->nullOnDelete();
            $table->foreignId('ingredient_category_id')->nullable()->after('supplier_id')->constrained('ingredient_categories')->nullOnDelete();
            $table->string('receiver_name', 100)->nullable()->after('ingredient_category_id');
            $table->foreignId('department_id')->nullable()->after('receiver_name')->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_templates', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['ingredient_category_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn(['supplier_id', 'ingredient_category_id', 'receiver_name', 'department_id']);
        });
    }
};
