<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Move sales attachments, supplier invoice scans and audit / corrective-action
 * photos from the public disk to the private one.
 *
 * On the public disk the web server served them to anyone holding the URL —
 * no login, no company check. New uploads already go to the private disk and
 * are streamed by PrivateFileController; this moves the ones uploaded before.
 * Deleting the public copy is the point: the old URLs must stop working.
 *
 * Paths do not change (both disks are keyed by the same relative path), so no
 * row is updated. Re-runnable: a file already on the private disk is skipped.
 * Reads fall back to the public disk (App\Support\PrivateFiles), so nothing
 * breaks if this is interrupted part-way.
 */
return new class extends Migration
{
    private const COLUMNS = [
        ['sales_record_attachments', 'file_path'],
        ['ai_invoice_scans', 'original_file_path'],
        ['procurement_invoices', 'original_file_path'],
        ['audit_finding_photos', 'file_path'],
        ['corrective_action_photos', 'file_path'],
    ];

    public function up(): void
    {
        $public  = Storage::disk('public');
        $private = Storage::disk('local');
        $moved = 0;
        $seen  = [];

        foreach (self::COLUMNS as [$table, $column]) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            DB::table($table)->whereNotNull($column)->orderBy('id')
                ->chunkById(500, function ($rows) use ($column, $public, $private, &$moved, &$seen) {
                    foreach ($rows as $row) {
                        $path = $row->{$column};

                        // A scan and the invoice made from it share one file.
                        if ($path === '' || isset($seen[$path])) {
                            continue;
                        }
                        $seen[$path] = true;

                        if (! $public->exists($path)) {
                            continue;   // already moved, or never there
                        }

                        if (! $private->exists($path)) {
                            $stream = $public->readStream($path);
                            $private->writeStream($path, $stream);
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }

                        // Only delete once the private copy is confirmed.
                        if ($private->exists($path) && $private->size($path) === $public->size($path)) {
                            $public->delete($path);
                            $moved++;
                        }
                    }
                });
        }

        Log::info("Moved {$moved} private upload(s) off the public disk.");
    }

    public function down(): void
    {
        // Deliberately not reversed: putting these back on the public disk
        // would republish them to anyone with the URL.
    }
};
