<?php

/**
 * Derives the Labels PWA icons from the supplied app icon.
 *
 * The artwork is NOT drawn here any more — public/labels-app/label-icon.png is
 * the designer's file and the source of truth, the same arrangement as
 * make-favicons.php has for the Servora icon. This only produces the sizes the
 * manifest and iOS ask for, and the maskable variant:
 *
 *   icon-192.png, icon-512.png   manifest `any`
 *   apple-touch-icon.png (180)   iOS ignores the manifest and reads this
 *   icon-maskable-512.png        Android crops it to a shape of its own, so
 *                                the plate bleeds to the edges and the whole
 *                                icon sits inset inside the safe zone — the
 *                                LABEL wordmark near its lower edge would be
 *                                cut off by a circle mask otherwise
 *
 * Re-run it after replacing the artwork, and bump the version in
 * Labels\StaffAppController::serviceWorker(), which caches the icons outright:
 *
 *   php scripts/make-label-app-icons.php
 */

$root = dirname(__DIR__);
$out  = $root . '/public/labels-app';
$src  = $out . '/label-icon.png';

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
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefilledrectangle($img, 0, 0, $size, $size, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagecopyresampled($img, $icon, 0, 0, 0, 0, $size, $size, imagesx($icon), imagesy($icon));

    return $img;
}

function save($img, string $path): void
{
    imagepng($img, $path);
    printf("  %-44s %d bytes\n", basename($path), filesize($path));
}

save(scaled($icon, 192), "$out/icon-192.png");
save(scaled($icon, 512), "$out/icon-512.png");
save(scaled($icon, 180), "$out/apple-touch-icon.png");

// Plate colour sampled from the artwork's own edge, so the bleed and the tile
// it surrounds are the same blue. Inset to 78% to clear the OS mask.
$plate = imagecolorat($icon, (int) round(imagesx($icon) * 0.06), (int) round(imagesy($icon) / 2));

$maskable = imagecreatetruecolor(512, 512);
imagesavealpha($maskable, true);
imagefilledrectangle($maskable, 0, 0, 512, 512, imagecolorallocate(
    $maskable,
    ($plate >> 16) & 0xFF,
    ($plate >> 8) & 0xFF,
    $plate & 0xFF,
));

$inner  = (int) round(512 * 0.78);
$offset = (int) round((512 - $inner) / 2);
imagealphablending($maskable, true);
imagecopy($maskable, scaled($icon, $inner), $offset, $offset, 0, 0, $inner, $inner);
save($maskable, "$out/icon-maskable-512.png");
