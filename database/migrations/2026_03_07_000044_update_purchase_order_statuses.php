<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Schema builder rather than raw "ALTER TABLE ... MODIFY COLUMN": that syntax is
        // MySQL-only, so this migration could not build the SQLite database the test suite
        // runs against. change() emits ENUM on MySQL and a CHECK-constrained varchar on
        // SQLite, so both drivers end up enforcing the same set.
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('status', ['draft','submitted','sent','approved','partial','received','cancelled'])
                ->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('status', ['draft','sent','partial','received','cancelled'])
                ->default('draft')->change();
        });
    }
};
