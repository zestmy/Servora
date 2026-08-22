<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who a help section is FOR, as distinct from whether it is finished.
 *
 * `is_published` was carrying both jobs and could only do one. "For
 * administrators" documents the platform's own screens — subscription states,
 * the invoice ledger, that impersonation exists — and the only way to keep it
 * off a public page was to unpublish it, which also hid it from the people it
 * was written for. Published-but-hidden is not a state that column can express.
 *
 * Three audiences, deliberately few:
 *
 *   public         anyone, signed in or not. The default, and right for almost
 *                  everything — most of what the manual answers is asked before
 *                  someone has an account.
 *   authenticated  any signed-in user of any tenant.
 *   system         system roles only, matching the audience of the screens the
 *                  section describes.
 *
 * A section the viewer may not see 404s rather than 403s. 403 confirms the
 * section exists and what it is called, and this is exactly the content where
 * that is worth not saying out loud.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doc_categories', function (Blueprint $table) {
            $table->string('visibility', 20)->default('public')->after('is_published');
        });

        // The section that prompted this. Its articles stay published — they
        // were never the problem — so republishing the section now shows it to
        // system roles and to nobody else.
        DB::table('doc_categories')
            ->where('slug', 'for-administrators')
            ->update(['visibility' => 'system', 'is_published' => true]);
    }

    public function down(): void
    {
        Schema::table('doc_categories', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
