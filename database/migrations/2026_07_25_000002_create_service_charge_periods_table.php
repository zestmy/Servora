<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_charge_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('amount', 12, 2);
            $table->decimal('mc_percent', 5, 2)->default(5);
            $table->decimal('abs_percent', 5, 2)->default(10);
            $table->timestamps();

            $table->unique(['company_id', 'outlet_id', 'period_from', 'period_to'], 'scp_company_outlet_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_charge_periods');
    }
};
