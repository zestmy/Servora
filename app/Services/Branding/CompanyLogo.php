<?php

namespace App\Services\Branding;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The company logo, and what a surface needs to know to display it.
 *
 * Lives outside the label module because every branded surface has the same
 * problem: the logo is whatever artwork the company uploaded, and the app
 * has no say in whether it is dark on transparent or light on transparent.
 * Put dark artwork on the LMS's dark sidebar and it vanishes; put light
 * artwork on the white login page and it vanishes just as completely. The
 * app cannot fix the artwork, so it measures it — see isDark() — and lets
 * each surface decide.
 *
 * The data URI half of this is label-specific and documented below.
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

    /**
     * Mean luminance below this counts as dark artwork.
     *
     * Set at 0.5 rather than something lower on purpose: the cost of calling
     * a mid-tone logo "dark" is a white chip behind it, which looks fine. The
     * cost of calling it light is an invisible logo, which is the bug being
     * fixed. Err towards the chip.
     */
    private const DARK_BELOW = 0.5;

    /** Grid resolution for the luminance sample, per axis. */
    private const SAMPLES = 64;

    /**
     * Public URL for on-screen use.
     *
     * Screens get a URL, not the data URI: a browser can fetch and cache the
     * file once across every page, which is the opposite of what a print
     * document needs.
     */
    public function url(?int $companyId): ?string
    {
        $path = $this->path($companyId);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Is the artwork dark, and therefore in trouble on a dark surface?
     *
     * Measured, not configured. A company uploads one logo and it has to
     * work on the LMS's near-black sidebar and on a white login page, and
     * nobody is going to maintain two variants — so the app looks at the
     * pixels instead of asking.
     *
     * Only pixels with real opacity count. Logos are overwhelmingly dark
     * artwork on a transparent background, and averaging the transparent
     * area in would drag every logo towards whatever GD calls those pixels
     * and make the answer meaningless.
     *
     * Returns null when there is no logo or it can't be read — callers treat
     * that as "no special treatment", which is what they did before this
     * existed.
     */
    public function isDark(?int $companyId): ?bool
    {
        $path = $this->path($companyId);

        if (! $path) {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);

        $key = 'brand-logo-dark:' . $this->stamp($companyId, $path, $absolute);

        // Cached as int: a false would be indistinguishable from a cache
        // miss on every driver that returns null for "not found".
        $cached = Cache::remember($key, self::TTL, function () use ($absolute) {
            $luma = $this->meanLuminance($absolute);

            return $luma === null ? -1 : (int) ($luma < self::DARK_BELOW);
        });

        return $cached < 0 ? null : (bool) $cached;
    }

    public function dataUri(?int $companyId): ?string
    {
        $path = $this->path($companyId);

        if (! $path) {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);

        return Cache::remember(
            'label-logo:' . $this->stamp($companyId, $path, $absolute),
            self::TTL,
            fn () => $this->encode($absolute)
        );
    }

    /** The stored path, or null if there isn't one or the file has gone. */
    private function path(?int $companyId): ?string
    {
        if (! $companyId) {
            return null;
        }

        $path = Company::withoutGlobalScopes()->whereKey($companyId)->value('logo');

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Cache discriminator. Size and mtime are in it so that replacing the
     * logo invalidates everything derived from it without anyone having to
     * remember to clear a cache.
     */
    private function stamp(int $companyId, string $path, string $absolute): string
    {
        return sprintf(
            '%d:%s:%d:%d',
            $companyId,
            md5($path),
            @filesize($absolute) ?: 0,
            @filemtime($absolute) ?: 0
        );
    }

    /**
     * Mean perceived luminance of the opaque pixels, 0 (black) to 1 (white).
     *
     * Sampled on a grid rather than pixel by pixel — a 2000px logo is 4
     * million reads for an answer that a few thousand samples already give
     * to well within the margin that matters here.
     */
    private function meanLuminance(string $absolute): ?float
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($absolute);
        $im  = $raw === false ? false : @imagecreatefromstring($raw);

        if (! $im) {
            return null;
        }

        try {
            $w = imagesx($im);
            $h = imagesy($im);

            $stepX = max(1, (int) floor($w / self::SAMPLES));
            $stepY = max(1, (int) floor($h / self::SAMPLES));

            $total = 0.0;
            $count = 0;

            for ($y = 0; $y < $h; $y += $stepY) {
                for ($x = 0; $x < $w; $x += $stepX) {
                    $rgba = imagecolorat($im, $x, $y);

                    // GD alpha runs 0 (opaque) to 127 (transparent).
                    if ((($rgba >> 24) & 0x7F) > 64) {
                        continue;
                    }

                    // Rec. 601 luma: green carries most of what an eye reads
                    // as brightness, so a plain RGB average would call a
                    // saturated blue logo lighter than it looks.
                    $total += (0.299 * (($rgba >> 16) & 0xFF)
                        + 0.587 * (($rgba >> 8) & 0xFF)
                        + 0.114 * ($rgba & 0xFF)) / 255;
                    $count++;
                }
            }

            // Fully transparent, or a single stray pixel: no honest answer.
            return $count > 8 ? $total / $count : null;
        } finally {
            imagedestroy($im);
        }
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
