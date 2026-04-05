<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ot_employees')) {
            Schema::create('ot_employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('position')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Drop old FK if it still exists, then add new one
        $fkExists = \DB::select("
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'overtime_claims'
              AND CONSTRAINT_NAME = 'overtime_claims_employee_id_foreign'
            LIMIT 1
        ");

        if ($fkExists) {
            Schema::table('overtime_claims', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });
        }

        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('ot_employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('overtime_claims', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::dropIfExists('ot_employees');
    }
};
