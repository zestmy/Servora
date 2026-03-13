<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_approvers', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('outlet_id')
                  ->constrained('departments')->nullOnDelete();
        });

        // MySQL won't let us drop the unique index while a FK references it.
        // Drop the FK on outlet_id first, then drop the unique, add the new one, re-add FK.
        Schema::table('po_approvers', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropUnique(['outlet_id', 'user_id']);
            $table->unique(['outlet_id', 'department_id', 'user_id']);
            $table->foreign('outlet_id')->references('id')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('po_approvers', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropUnique(['outlet_id', 'department_id', 'user_id']);
            $table->unique(['outlet_id', 'user_id']);
            $table->foreign('outlet_id')->references('id')->on('outlets')->cascadeOnDelete();
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
