<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One photograph per asset.
 *
 * A COLUMN, NOT A GALLERY TABLE like `recipe_images`. A recipe's photos are
 * plating references and there are several by design — dine-in, takeaway. An
 * asset's photo answers one question, "is this the one?", and it is asked by
 * somebody walking an outlet with a count sheet trying to tell two mixing bowls
 * apart. A second picture adds nothing to that, and a gallery would add a table,
 * a sort order and a screen to manage it.
 *
 * THE PUBLIC DISK, unlike the employee photograph beside it in the tree. Staff
 * photos are identity documents and go to `local` behind an authorised route.
 * This is a picture of a knife: it is served straight off `public` like recipe
 * photos and outlet logos, which is what lets the count sheet draw forty of them
 * without forty authorised requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
