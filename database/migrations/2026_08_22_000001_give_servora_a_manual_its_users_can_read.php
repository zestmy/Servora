<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The help centre.
 *
 * Deliberately NOT the `pages` table. A CMS page is a standalone document with
 * a slug and a menu placement; a manual is a tree — categories that order
 * their articles, articles that carry figures, and a search that has to know
 * which is which. Bolting a parent_id onto `pages` would have given every
 * footer link a nullable category and every help article a menu_placement
 * nobody reads.
 *
 * Not company-scoped: this is Servora's own manual, written once by the
 * platform and read by every tenant (and by anyone evaluating the product,
 * hence the public route).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            // An <x-icon> name. Nullable, but the index renders a placeholder
            // tile without one — same failure mode as a nav group with no icon.
            $table->string('icon', 60)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('doc_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // Unique across the whole manual, not per category: the article
            // route resolves on the article slug alone, so that a category can
            // be renamed or an article moved without breaking a shared link.
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body')->nullable();
            // Extra search terms that are not in the prose — the words a user
            // types ("wastage", "GRN", "SST") when the article calls it
            // something else.
            $table->string('keywords', 500)->nullable();
            $table->string('hero_image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('view_count')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['doc_category_id', 'is_published', 'sort_order']);
        });

        Schema::create('doc_images', function (Blueprint $table) {
            $table->id();
            // Nullable: an uploaded figure outlives the draft it was made for,
            // and the library tab lists the unattached ones for reuse.
            $table->foreignId('doc_article_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt', 300)->nullable();
            $table->string('caption', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('doc_article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_images');
        Schema::dropIfExists('doc_articles');
        Schema::dropIfExists('doc_categories');
    }
};
