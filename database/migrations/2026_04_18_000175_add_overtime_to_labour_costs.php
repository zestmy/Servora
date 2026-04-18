<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labour_costs', function (Blueprint $table) {
            if (! Schema::hasColumn('labour_costs', 'overtime')) {
                $table->decimal('overtime', 14, 2)->default(0)->after('service_point');
            }
        });
    }

    public function down(): void
    {
        Schema::table('labour_costs', function (Blueprint $table) {
            if (Schema::hasColumn('labour_costs', 'overtime')) {
                $table->dropColumn('overtime');
            }
        });
    }
};
