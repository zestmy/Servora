<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an automatic disposal came from.
 *
 * Issuing a credit note that returns or rejects-as-damaged an asset now
 * takes it out of the register as well as off the invoice. The link is what
 * makes that safe to run more than once: one credit note can only ever own
 * one disposal, the same way one GRN owns one receipt
 * (`goods_received_note_id`, added in phase two).
 *
 * It is also the answer to "why is this gone?" — the register can point at
 * the note that removed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('asset_movements', 'credit_note_id')) {
            Schema::table('asset_movements', function (Blueprint $table) {
                $table->foreignId('credit_note_id')->nullable()->after('goods_received_note_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('asset_movements', function (Blueprint $table) {
            $table->dropForeign(['credit_note_id']);
            $table->dropColumn('credit_note_id');
        });
    }
};
