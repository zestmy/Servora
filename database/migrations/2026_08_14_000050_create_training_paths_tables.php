<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Learning paths — an ordered run of courses a new hire works through.
 *
 * The difference between a path and a folder of courses is the ORDER and the
 * gate: "Food Safety Basics" has to be done before "Handling Allergens", and a
 * path that cannot enforce that is just a list. `unlocks_sequentially` is the
 * flag that decides whether the second item is reachable before the first is
 * passed, because induction paths want the gate and refresher paths do not.
 *
 * Progress is stored per (path, trainee) rather than derived on read. It could
 * be derived — the attempts are all there — but the derivation is a join across
 * every item's quiz for every trainee on every dashboard render, and the
 * completion date is a fact worth keeping even after a course is retired from
 * the path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft'); // draft | published | archived
            $table->boolean('unlocks_sequentially')->default(true);
            // Shown on the trainee's path card: "finish within 14 days".
            $table->unsignedSmallInteger('target_days')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        Schema::create('training_path_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_course_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['training_path_id', 'training_course_id'], 'training_path_item_unique');
            $table->index(['training_path_id', 'sort_order']);
        });

        Schema::create('training_path_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lms_user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('completed_items')->default(0);
            $table->unsignedSmallInteger('total_items')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['training_path_id', 'lms_user_id'], 'training_path_progress_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_path_progress');
        Schema::dropIfExists('training_path_items');
        Schema::dropIfExists('training_paths');
    }
};
