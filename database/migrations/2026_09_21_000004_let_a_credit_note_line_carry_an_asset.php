<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A credit note line can name an asset.
 *
 * The last document in the purchasing chain that could not. An asset can be
 * requested, ordered, delivered and received; until now the one thing that
 * could not be done was credit it back when the supplier sent the wrong
 * mixer or billed for two and delivered one.
 *
 * A CREDIT NOTE IS FINANCIAL ONLY, and stays that way. Issuing one has never
 * moved ingredient stock — it records what the supplier is crediting, not
 * what is on the shelf — so an asset line does not move the register either.
 * Anything else would make assets behave differently from everything else on
 * the same document. Where a returned asset should also leave the register,
 * that is a disposal, recorded under Assets ▸ Records with the reason
 * "Returned to supplier", and the form says so.
 *
 * That separation is also what stops the obvious double-count: a `rejected`
 * or `short_delivery` line describes something that never entered the
 * register in the first place, because the GRN only ever receives what
 * actually arrived in a countable condition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_note_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable()->change();
        });

        if (! Schema::hasColumn('credit_note_lines', 'asset_id')) {
            Schema::table('credit_note_lines', function (Blueprint $table) {
                $table->foreignId('asset_id')->nullable()->after('ingredient_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        DB::table('credit_note_lines')->whereNull('ingredient_id')->delete();

        Schema::table('credit_note_lines', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });

        Schema::table('credit_note_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->nullable(false)->change();
        });
    }
};
