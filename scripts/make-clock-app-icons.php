<?php

/**
 * Generates the clock-in PWA icons: a brand-teal rounded square with a white
 * clock face, matching the app header fill.
 *
 * Drawn rather than shipped as binary so the shape stays editable in the repo
 * and there is no opaque asset nobody can regenerate. Companion to
 * make-label-app-icons.php.
 *
 *   php scripts/make-clock-app-icons.php
 */

$out = __DIR__ . '/../public/clock-app';

if (! is_dir($out)) {
    mkdir($out, 0755, true);
}

/** Rounded rectangle, filled. */
function roundedRect($img, int $x, int $y, int $w, int $h, int $r, int $colour): void
{
    imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $colour);
    imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $colour);
    foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r],
              [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as [$cx, $cy]) {
        imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $colour);
    }
}

function makeIcon(int $size, string $path, bool $maskable = false): void
{
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $teal  = imagecolorallocate($img, 13, 95, 97);      // brand-700, the header fill
    $white = imagecolorallocate($img, 255, 255, 255);

    // Maskable icons get cropped to the OS's own shape, so the background
    // must bleed to the edges and the glyph must sit inside the safe zone.
    $radius = $maskable ? 0 : (int) round($size * 0.22);
    roundedRect($img, 0, 0, $size - 1, $size - 1, $radius, $teal);

    $cx      = (int) round($size / 2);
    $cy      = (int) round($size / 2);
    $diameter = (int) round($size * ($maskable ? 0.52 : 0.64));

    imagefilledellipse($img, $cx, $cy, $diameter, $diameter, $white);

    // Hands at twelve and three, not the ten-past-ten of watch advertising:
    // a symmetrical V reads as a tick at 24 pixels, and this icon sits next
    // to a labels app whose glyph is already a shape people have to tell
    // apart at a glance.
    imagesetthickness($img, max(2, (int) round($size * 0.05)));

    $hour   = $diameter * 0.30;
    $minute = $diameter * 0.38;

    imageline($img, $cx, $cy, $cx, (int) round($cy - $hour), $teal);
    imageline($img, $cx, $cy, (int) round($cx + $minute), $cy, $teal);

    $pin = max(3, (int) round($size * 0.035));
    imagefilledellipse($img, $cx, $cy, $pin, $pin, $teal);

    imagepng($img, $path);
    imagedestroy($img);

    printf("  %-44s %d bytes\n", basename($path), filesize($path));
}

makeIcon(192, "$out/icon-192.png");
makeIcon(512, "$out/icon-512.png");
makeIcon(512, "$out/icon-maskable-512.png", true);
makeIcon(180, "$out/apple-touch-icon.png");

echo "done\n";
