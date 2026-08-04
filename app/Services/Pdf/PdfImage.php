<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Stored image → data URI sized for a print document.
 *
 * dompdf holds the whole document in memory and then concatenates it into a
 * single string to emit it, so the peak is roughly twice the finished PDF.
 * The SOP exports embed every photo as base64, and base64 costs a third on
 * top of the file — so a category export of 61 recipes carrying 25 MB of
 * camera-resolution photos blew the 256 MB limit inside Cpdf::output().
 *
 * The images were never needed at that size. The blades cap a hero photo at
 * 200px, a plating shot at 220px and a step thumbnail at 130px, so a 1545×1600
 * upload is being thrown away at render time after being paid for twice in
 * memory. Downscaling first is not lossy in any way the reader can see.
 *
 * The logo is worse than its file size suggests: it repeats in the page header
 * of every recipe, so on a 203-recipe export a 253 KB logo is 68 MB of base64
 * in the HTML string before dompdf has parsed anything. It gets its own, much
 * smaller cap.
 *
 * Results are cached against size+mtime so that re-exporting — which is what
 * people actually do — skips the decode entirely, and replacing an image
 * invalidates it without anyone clearing a cache.
 *
 * Nothing here throws. An unreadable or undecodable file returns null and the
 * caller omits it, which is how these exports already treated missing files.
 */
class PdfImage
{
    /**
     * Photos render at 220px at the very largest. 900 leaves headroom for a
     * reader zooming in without carrying the original 1600px.
     */
    private const MAX_PHOTO_PX = 900;

    /** The logo renders at 110px on the cover, 68px in each page header. */
    private const MAX_LOGO_PX = 240;

    private const QUALITY = 82;

    private const TTL = 86400;

    /** A recipe, plating or step photo. Flattened and re-encoded as JPEG. */
    public function photo(?string $path): ?string
    {
        return $this->dataUri($path, self::MAX_PHOTO_PX, false);
    }

    /** The company logo. Stays PNG — it is usually artwork on transparency. */
    public function logo(?string $path): ?string
    {
        return $this->dataUri($path, self::MAX_LOGO_PX, true);
    }

    private function dataUri(?string $path, int $maxPx, bool $keepAlpha): ?string
    {
        if (! $path) {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);

        if (! is_file($absolute)) {
            return null;
        }

        $key = sprintf(
            'pdf-image:%s:%d:%d:%d',
            md5($path),
            $maxPx,
            @filesize($absolute) ?: 0,
            @filemtime($absolute) ?: 0
        );

        // Cached as '' rather than null for the miss: every driver that
        // returns null for "not found" would re-decode a broken file on
        // every export otherwise.
        $cached = Cache::remember(
            $key,
            self::TTL,
            fn () => $this->encode($absolute, $maxPx, $keepAlpha) ?? ''
        );

        return $cached === '' ? null : $cached;
    }

    private function encode(string $absolute, int $maxPx, bool $keepAlpha): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($absolute);

        if ($raw === false) {
            return null;
        }

        $source = @imagecreatefromstring($raw);

        // Free the original bytes before allocating the target — on a 25 MB
        // batch the two together are what there isn't room for.
        unset($raw);

        if (! $source) {
            return null;
        }

        try {
            $w = imagesx($source);
            $h = imagesy($source);

            if ($w < 1 || $h < 1) {
                return null;
            }

            $scale = min(1, $maxPx / max($w, $h));
            $tw    = max(1, (int) round($w * $scale));
            $th    = max(1, (int) round($h * $scale));

            $target = imagecreatetruecolor($tw, $th);

            if ($keepAlpha) {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            } else {
                // JPEG has no alpha. Without this, a transparent PNG comes out
                // on black, and these sit on a white page.
                imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
                imagealphablending($target, true);
            }

            imagecopyresampled($target, $source, 0, 0, 0, 0, $tw, $th, $w, $h);

            ob_start();

            if ($keepAlpha) {
                imagepng($target, null, 9);
                $mime = 'image/png';
            } else {
                imagejpeg($target, null, self::QUALITY);
                $mime = 'image/jpeg';
            }

            $bytes = (string) ob_get_clean();

            imagedestroy($target);

            return $bytes === '' ? null : "data:{$mime};base64," . base64_encode($bytes);
        } finally {
            imagedestroy($source);
        }
    }
}
