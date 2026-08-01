<?php

namespace App\Services\Labels;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The company logo, prepared for a label.
 *
 * Returns a data URI rather than a URL or a filesystem path, because one
 * value has to work in both output paths: the browser prints HTML from the
 * chef's machine, and dompdf renders the same markup server-side. A URL
 * would need dompdf's remote fetching switched on and the server able to
 * reach its own public URL; a local path would render in the PDF and break
 * in the browser. A data URI needs neither.
 *
 * The image is downscaled first, and that is not cosmetic. The renderer
 * repeats every field once per physical label, so an unscaled logo would be
 * base64'd into a 30-label batch thirty times — megabytes of PDF pushed to
 * PrintNode to print something 12 mm wide. At 203 dpi a 12 mm logo is about
 * 96 pixels, so MAX_PX is generous already.
 *
 * Nothing here throws. A missing file, an unreadable image or a company with
 * no logo all mean "print the label without one" — the renderer drops any
 * field whose value is empty, so the label simply comes out as it did before.
 */
class CompanyLogo
{
    /** Roughly 2× what a 203 dpi thermal head can resolve at label size. */
    private const MAX_PX = 200;

    private const TTL = 86400;

    public function dataUri(?int $companyId): ?string
    {
        if (! $companyId) {
            return null;
        }

        $path = Company::withoutGlobalScopes()->whereKey($companyId)->value('logo');

        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $absolute = $disk->path($path);

        // Size and mtime in the key: replacing the logo has to invalidate
        // this without anyone remembering to clear a cache.
        $key = sprintf(
            'label-logo:%d:%s:%d:%d',
            $companyId,
            md5($path),
            @filesize($absolute) ?: 0,
            @filemtime($absolute) ?: 0
        );

        return Cache::remember($key, self::TTL, fn () => $this->encode($absolute));
    }

    /** Downscale to MAX_PX and re-encode as PNG, or null if GD can't. */
    private function encode(string $absolute): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($absolute);

        if ($raw === false) {
            return null;
        }

        $source = @imagecreatefromstring($raw);

        if (! $source) {
            return null;
        }

        try {
            $w = imagesx($source);
            $h = imagesy($source);

            if ($w < 1 || $h < 1) {
                return null;
            }

            $scale = min(1, self::MAX_PX / max($w, $h));
            $tw    = max(1, (int) round($w * $scale));
            $th    = max(1, (int) round($h * $scale));

            $target = imagecreatetruecolor($tw, $th);

            // Logos are usually transparent PNGs sitting on a white label.
            // Without this the transparent area comes out solid black, which
            // on a thermal printer is a 12 mm block of wasted ribbon.
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));

            imagecopyresampled($target, $source, 0, 0, 0, 0, $tw, $th, $w, $h);

            ob_start();
            imagepng($target, null, 9);
            $png = (string) ob_get_clean();

            imagedestroy($target);

            return $png === '' ? null : 'data:image/png;base64,' . base64_encode($png);
        } finally {
            imagedestroy($source);
        }
    }
}
