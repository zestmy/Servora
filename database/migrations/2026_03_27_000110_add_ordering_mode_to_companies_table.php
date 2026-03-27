<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->enum('ordering_mode', ['direct', 'cpu'])->default('direct')->after('require_po_approval');
            $table->boolean('require_pr_approval')->default(false)->after('ordering_mode');
            $table->char('default_tax_country', 2)->nullable()->after('require_pr_approval');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['ordering_mode', 'require_pr_approval', 'default_tax_country']);
        });
    }
};
