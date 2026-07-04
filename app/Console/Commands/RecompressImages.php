<?php

namespace App\Console\Commands;

use App\Models\RecipeImage;
use App\Models\RecipeStep;
use App\Services\ImageStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off / re-runnable maintenance: recompress existing recipe photos and
 * SOP step images in place (auto-orient, strip EXIF, cap longest side at
 * 1600px, re-encode). Paths never change, so no references break; a file is
 * only overwritten when the result is at least 10% smaller. Sales attachments
 * are intentionally untouched — receipts are financial records.
 */
class RecompressImages extends Command
{
    protected $signature = 'images:recompress {--dry-run : Report savings without changing any file}';

    protected $description = 'Recompress existing recipe & SOP step images in place (resize + re-encode)';

    public function handle(): int
    {
        if (! ImageStorageService::imagickAvailable()) {
            $this->error('The imagick extension is not available.');
            return self::FAILURE;
        }

        $dry  = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        $processed = 0;
        $shrunk = 0;
        $skipped = 0;
        $missing = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;

        // RecipeImage rows carry a stored file_size we keep in sync.
        foreach (RecipeImage::query()->cursor() as $image) {
            $result = $this->recompress($disk, $image->file_path, $dry);
            $processed++;

            match ($result['status']) {
                'shrunk'  => $shrunk++,
                'skipped' => $skipped++,
                'missing' => $missing++,
            };

            $bytesBefore += $result['before'];
            $bytesAfter  += $result['after'];

            if ($result['status'] === 'shrunk' && ! $dry) {
                $image->update(['file_size' => $result['after']]);
            }
        }

        // Step images have no size column — in-place overwrite only.
        foreach (RecipeStep::whereNotNull('image_path')->cursor() as $step) {
            $result = $this->recompress($disk, $step->image_path, $dry);
            $processed++;

            match ($result['status']) {
                'shrunk'  => $shrunk++,
                'skipped' => $skipped++,
                'missing' => $missing++,
            };

            $bytesBefore += $result['before'];
            $bytesAfter  += $result['after'];
        }

        $savedMb = round(($bytesBefore - $bytesAfter) / 1048576, 1);
        $mode = $dry ? '[DRY RUN] would save' : 'saved';

        $this->info("Processed {$processed} images: {$shrunk} recompressed, {$skipped} already small, {$missing} missing files.");
        $this->info(ucfirst($mode) . " {$savedMb} MB (" . round($bytesBefore / 1048576, 1) . ' MB → ' . round($bytesAfter / 1048576, 1) . ' MB).');

        return self::SUCCESS;
    }

    /**
     * @return array{status: 'shrunk'|'skipped'|'missing', before: int, after: int}
     */
    private function recompress($disk, ?string $path, bool $dry): array
    {
        if (! $path || ! $disk->exists($path)) {
            return ['status' => 'missing', 'before' => 0, 'after' => 0];
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $before = $disk->size($path);

        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return ['status' => 'skipped', 'before' => $before, 'after' => $before];
        }

        try {
            $img = new \Imagick($disk->path($path));

            if (method_exists($img, 'autoOrient')) {
                $img->autoOrient();
            } elseif (method_exists($img, 'autoOrientImage')) {
                $img->autoOrientImage();
            }
            $img->stripImage();

            $w = $img->getImageWidth();
            $h = $img->getImageHeight();
            $max = ImageStorageService::MAX_DIMENSION;
            if (max($w, $h) > $max) {
                $img->resizeImage($w >= $h ? $max : 0, $h > $w ? $max : 0, \Imagick::FILTER_LANCZOS, 1);
            }

            // Keep the extension/format so the stored path stays truthful.
            $img->setImageFormat($ext === 'png' ? 'png' : ($ext === 'webp' ? 'webp' : 'jpeg'));
            $img->setImageCompressionQuality(ImageStorageService::QUALITY);
            $blob = $img->getImageBlob();
            $img->clear();
        } catch (\Throwable $e) {
            $this->warn("Failed: {$path} — {$e->getMessage()}");
            return ['status' => 'skipped', 'before' => $before, 'after' => $before];
        }

        $after = strlen($blob);

        // Only overwrite when it meaningfully shrinks; re-encoding an already
        // optimised file would just degrade it for no gain.
        if ($after >= $before * 0.9) {
            return ['status' => 'skipped', 'before' => $before, 'after' => $before];
        }

        if (! $dry) {
            $disk->put($path, $blob);
        }

        return ['status' => 'shrunk', 'before' => $before, 'after' => $after];
    }
}
