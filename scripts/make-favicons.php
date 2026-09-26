<?php

/**
 * Derives the browser and PWA icons from the supplied app icon.
 *
 * The brand artwork itself is NOT generated — public/images/servora-icon.png
 * and the servora-logo-*.png lockups are the designer's files and are the
 * source of truth. This script only produces the two formats that cannot be
 * shipped as a plain PNG:
 *
 *   favicon.ico              16/32/48, for browsers that fetch /favicon.ico
 *                            without reading the <link> tags
 *   apple-touch-icon.png     iOS's fallback for pages with no tag of their own
 *   servora-maskable-512.png Android's maskable icon, which is cropped to a
 *                            shape the OS chooses, so the background has to
 *                            bleed to the edges and the glyph has to sit
 *                            inside the safe zone
 *
 * Re-run it after replacing the app icon:
 *
 *   php scripts/make-favicons.php
 */

$root = dirname(__DIR__);
$src  = $root . '/public/images/servora-icon.png';

if (! is_file($src)) {
    fwrite(STDERR, "Missing source icon: $src\n");
    exit(1);
}

$icon = imagecreatefrompng($src);
imagealphablending($icon, false);
imagesavealpha($icon, true);

/** Resamples the source icon to a square of $size, alpha preserved. */
function scaled($icon, int $size)
{
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefilledrectangle($out, 0, 0, $size, $size, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $icon, 0, 0, 0, 0, $size, $size, imagesx($icon), imagesy($icon));

    return $out;
}

/** PNG bytes, without touching disk. */
function pngBytes($img): string
{
    ob_start();
    imagepng($img);

    return (string) ob_get_clean();
}

/**
 * Packs PNGs into an .ico.
 *
 * GD cannot write .ico, so the container is assembled by hand. PNG-compressed
 * entries rather than BMP: every browser that still asks for favicon.ico
 * understands them, and they are a third of the size.
 */
function writeIco(array $pngs, string $path): void
{
    $count  = count($pngs);
    $offset = 6 + $count * 16;
    $dir    = '';
    $blobs  = '';

    foreach ($pngs as $size => $blob) {
        $dir .= pack('CCCCvvVV', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size, 0, 0, 1, 32, strlen($blob), $offset);
        $offset += strlen($blob);
        $blobs  .= $blob;
    }

    file_put_contents($path, pack('vvv', 0, 1, $count) . $dir . $blobs);
}

writeIco(
    [16 => pngBytes(scaled($icon, 16)), 32 => pngBytes(scaled($icon, 32)), 48 => pngBytes(scaled($icon, 48))],
    $root . '/public/favicon.ico',
);

/**
 * /apple-touch-icon.png — what iOS fetches from the site root when a page
 * carries no apple-touch-icon tag of its own. Without it "Add to Home Screen"
 * on such a page draws a grey tile with the first letter of the title.
 */
imagepng(scaled($icon, 180), $root . '/public/apple-touch-icon.png');

/**
 * The maskable icon.
 *
 * The plate colour is sampled from the artwork rather than taken from the
 * brand token, so the bled background and the icon it surrounds are the same
 * blue even if the two ever drift apart. The glyph is inset to 78%, which
 * keeps it clear of the 20% every side can lose to the OS mask.
 */
$plate = imagecolorat($icon, (int) round(imagesx($icon) * 0.06), (int) round(imagesy($icon) / 2));

$maskable = imagecreatetruecolor(512, 512);
imagealphablending($maskable, false);
imagesavealpha($maskable, true);
imagefilledrectangle($maskable, 0, 0, 512, 512, imagecolorallocate(
    $maskable,
    ($plate >> 16) & 0xFF,
    ($plate >> 8) & 0xFF,
    $plate & 0xFF,
));

$inner  = (int) round(512 * 0.78);
$glyph  = scaled($icon, $inner);
$offset = (int) round((512 - $inner) / 2);
imagealphablending($maskable, true);
imagecopy($maskable, $glyph, $offset, $offset, 0, 0, $inner, $inner);
imagepng($maskable, $root . '/public/servora-maskable-512.png');

printf(
    "Derived from %s:\n  public/favicon.ico (%.1f KB)\n  public/servora-maskable-512.png (%.1f KB)\n",
    str_replace($root . '/', '', $src),
    filesize($root . '/public/favicon.ico') / 1024,
    filesize($root . '/public/servora-maskable-512.png') / 1024,
);
