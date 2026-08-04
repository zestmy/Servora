<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracking rows for queued SOP PDF exports.
     *
     * The whole-catalogue export renders ~500 MB and ~100 s, which no
     * synchronous request on this box can hold, so it moves to the queue and
     * the Training Portal watches a row here instead of a response.
     *
     * The status lives in a table rather than the cache because it has to
     * survive a page reload and a worker restart: someone kicks off an export,
     * closes the tab, and comes back for the file. That also gives the file a
     * home to be pruned from, which a cache entry would not.
     *
     * filters is the same four-key array the synchronous exports accept, so a
     * category export can be queued later without a schema change.
     */
    public function up(): void
    {
        Schema::create('sop_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('status', 20)->default('queued');
            $table->string('label')->nullable();
            $table->json('filters')->nullable();

            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('progress_label')->nullable();
            $table->string('phase', 20)->nullable();

            $table->string('file_path')->nullable();
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('recipe_count')->nullable();

            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // The panel's only read: this company's most recent export.
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sop_exports');
    }
};
