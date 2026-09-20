<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A purchase request line can name an asset instead of an ingredient.
 *
 * ON THE EXISTING REQUEST, not a second document. Somebody asking for a new
 * stand mixer is doing the same thing as somebody asking for 20kg of flour —
 * it goes to the same approver, through the same queue — and a parallel
 * "asset purchase request" would be a second inbox for the same person to
 * watch, with its own approval rules to keep in step.
 *
 * ADDITIVE AND INERT TO EXISTING CODE. An asset line carries no
 * `ingredient_id`, and both consolidation paths in PurchaseRequestService
 * already skip a line without one (that is how hand-typed custom items have
 * always behaved), so an asset can never be folded into a food PO by accident.
 * The preview now COUNTS them instead of dropping them silently — see
 * `asset_line_count` there — because a line that vanishes without a word is
 * how a request gets approved and then forgotten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('ingredient_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });
    }
};
