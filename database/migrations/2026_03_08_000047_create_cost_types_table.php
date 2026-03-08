<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 30)->index();
            $table->string('color', 7)->default('#6b7280');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
        });

        // Seed default cost types for every existing company
        $companies = \Illuminate\Support\Facades\DB::table('companies')->pluck('id');
        $now = now();

        $defaults = [
            ['name' => 'Food',        'slug' => 'food',        'color' => '#22c55e', 'sort_order' => 1],
            ['name' => 'Beverage',     'slug' => 'beverage',    'color' => '#3b82f6', 'sort_order' => 2],
            ['name' => 'Merchandise',  'slug' => 'merchandise', 'color' => '#a855f7', 'sort_order' => 3],
            ['name' => 'Retail',       'slug' => 'retail',      'color' => '#f97316', 'sort_order' => 4],
            ['name' => 'Other',        'slug' => 'other',       'color' => '#6b7280', 'sort_order' => 5],
        ];

        foreach ($companies as $companyId) {
            foreach ($defaults as $d) {
                \Illuminate\Support\Facades\DB::table('cost_types')->insert(array_merge($d, [
                    'company_id' => $companyId,
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_types');
    }
};
