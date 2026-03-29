<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('workspace_mode', ['outlet', 'kitchen'])->default('outlet')->after('can_view_all_outlets');
            $table->foreignId('default_kitchen_id')->nullable()->after('workspace_mode')
                ->constrained('central_kitchens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_kitchen_id']);
            $table->dropColumn(['workspace_mode', 'default_kitchen_id']);
        });
    }
};
