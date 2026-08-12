<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Removing a row removes the file it points at.
 *
 * The rule this enforces is already written on EmployeeDocument: the file IS
 * the record, so a row deleted while its file stays behind turns the delete
 * into a lie. What it adds is doing that consistently, because eleven models
 * owned a file and only a handful cleaned up after themselves.
 *
 * WHY IT SEARCHES FOR THE DISK RATHER THAN NAMING ONE. This product stores on
 * two: scanned documents and employee paperwork go to `local` because they are
 * identity documents, while recipe photographs, avatars and logos go to
 * `public` so they can be served directly. Naming the wrong one in a cleanup
 * hook fails SILENTLY — Storage::delete() on a path the disk does not have
 * returns without complaint — which produces exactly the orphan the hook was
 * added to prevent, and produces it invisibly. Asking which disk actually
 * holds the file cannot get that wrong.
 */
trait PurgesStoredFiles
{
    /**
     * Delete a stored file from whichever disk holds it.
     *
     * A path nothing holds is not an error: the file may already be gone, and
     * a delete must never fail because the tidying had nothing to tidy.
     */
    protected static function purgeStoredFile(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        foreach (['public', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);

                return;
            }
        }
    }

    /**
     * Whether another row still points at the same file.
     *
     * Duplication is why this exists. Recipes\Index::copyStoredFile() copies a
     * recipe's images to fresh paths, but returns the ORIGINAL path unchanged
     * when the source file is missing — so two rows can legitimately share
     * one. Deleting the copy would then take the original's picture with it,
     * and nothing would report the loss.
     *
     * Global scopes are dropped deliberately: a row in another company holding
     * the same path still counts, and the question here is whether the FILE is
     * still needed, not whether the asker may see the row that needs it.
     */
    protected function storedFileIsSharedElsewhere(string $column, ?string $path): bool
    {
        if (blank($path)) {
            return false;
        }

        return static::withoutGlobalScopes()
            ->where($column, $path)
            ->whereKeyNot($this->getKey())
            ->exists();
    }

    /**
     * The whole rule in one call, for a model's delete hook: remove the file
     * unless something else still points at it.
     */
    protected function purgeOwnedFile(string $column): void
    {
        $path = $this->{$column};

        if (! $this->storedFileIsSharedElsewhere($column, $path)) {
            static::purgeStoredFile($path);
        }
    }
}
