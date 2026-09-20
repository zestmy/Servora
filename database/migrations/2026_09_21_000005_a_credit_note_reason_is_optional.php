<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `credit_notes.reason` is optional, and the column now says so.
 *
 * Found while adding asset lines, not looked for. The form has always
 * validated it as `nullable` and `CreditNoteForm::save()` writes
 * `$this->reason ?: null`, but the column was created NOT NULL with no
 * default — so issuing a note without typing a reason inserted NULL into a
 * NOT NULL column. Production runs STRICT_TRANS_TABLES, which makes that a
 * hard error rather than a coerced empty string: a 500 on save.
 *
 * The same shape as the `source` enum that could not hold 'asset' — code and
 * column disagreeing, with the column winning at the worst moment. It has
 * never been hit because there are no credit notes in production yet, which
 * is precisely why it is worth closing before there are.
 *
 * `reason` is the free-text "why" on the note as a whole; the per-line
 * `reason_code` is the required one and stays NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            // text, not string: the column has always been TEXT and narrowing it
            // to a varchar is a data change nobody asked for.
            $table->text('reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        // A NOT NULL column cannot hold the rows that were allowed to be null.
        DB::table('credit_notes')->whereNull('reason')->update(['reason' => '']);

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->text('reason')->nullable(false)->change();
        });
    }
};
