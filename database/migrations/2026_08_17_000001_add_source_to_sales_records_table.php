<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Where a sales record came from: hand-typed, the Zeoniq Excel upload, the
// Z-report OCR, or (soon) the POS sync agent. The automated sync needs this
// to replace its OWN earlier writes on a re-export without ever deleting a
// record a human typed — "manual" as the default is correct for every
// pre-existing row, all of which had a person behind them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
