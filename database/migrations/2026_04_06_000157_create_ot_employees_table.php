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
        $fkExists = $this->foreignKeyExistsOn('overtime_claims', 'employee_id');

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

    /**
     * Driver-agnostic foreign-key lookup, by the column it constrains.
     *
     * This was a query against information_schema.TABLE_CONSTRAINTS, which exists only on
     * MySQL — so the migration could not run on the SQLite connection the test suite uses,
     * and every RefreshDatabase test failed while building its database rather than on any
     * assertion of its own.
     *
     * Then it matched on the CONSTRAINT NAME, which SQLite does not record: every lookup
     * came back false there, the old key was never dropped, and the new one was added
     * beside it. A row then had to satisfy both — so an overtime claim could only be
     * inserted when the employee id happened to also be a user id, which is why this table
     * had no tests. Match on the column instead; every driver knows that much.
     */
    private function foreignKeyExistsOn(string $table, string $column): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach (Schema::getForeignKeys($table) as $fk) {
            if (($fk['columns'] ?? []) === [$column]) {
                return true;
            }
        }

        return false;
    }

};
