<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ingredient_categories', 'type')) {
            Schema::table('ingredient_categories', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        if (Schema::hasColumn('sales_categories', 'type')) {
            Schema::table('sales_categories', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        Schema::dropIfExists('cost_types');
    }

    public function down(): void
    {
        Schema::create('cost_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 50);
            $table->string('name', 100);
            $table->string('color', 7)->default('#6b7280');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'slug']);
        });

        Schema::table('ingredient_categories', function (Blueprint $table) {
            $table->string('type', 50)->nullable()->after('parent_id');
        });

        Schema::table('sales_categories', function (Blueprint $table) {
            $table->string('type', 50)->nullable()->after('name');
        });
    }
};
