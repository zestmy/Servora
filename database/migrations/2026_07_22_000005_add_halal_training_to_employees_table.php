<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('halal_training')->default(false)->after('typhoid_expired_on');
            $table->date('halal_training_date')->nullable()->after('halal_training');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['halal_training', 'halal_training_date']);
        });
    }
};
