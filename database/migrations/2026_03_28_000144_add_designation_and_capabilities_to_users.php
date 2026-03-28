<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('designation', 100)->nullable()->after('email');
            $table->boolean('can_manage_users')->default(false)->after('designation');
            $table->boolean('can_approve_po')->default(false)->after('can_manage_users');
            $table->boolean('can_approve_pr')->default(false)->after('can_approve_po');
            $table->boolean('can_delete_records')->default(false)->after('can_approve_pr');
            $table->boolean('can_view_all_outlets')->default(false)->after('can_delete_records');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['designation', 'can_manage_users', 'can_approve_po', 'can_approve_pr', 'can_delete_records', 'can_view_all_outlets']);
        });
    }
};
