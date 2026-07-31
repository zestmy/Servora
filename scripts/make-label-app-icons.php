<?php

/**
 * Generates the PWA icons: an indigo rounded square with a white luggage-tag
 * glyph, matching the app's header colour and the tag icon used in the
 * Servora sidebar.
 *
 * Drawn rather than shipped as binary so the shape stays editable in the
 * repo and there is no opaque asset nobody can regenerate.
 */

$out = __DIR__ . '/../public/labels-app';

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

    $indigo = imagecolorallocate($img, 79, 70, 229);   // indigo-600
    $white  = imagecolorallocate($img, 255, 255, 255);

    // Maskable icons get cropped to a circle by the OS, so the background
    // must bleed to the edges and the glyph must sit inside the safe zone.
    $radius = $maskable ? 0 : (int) round($size * 0.22);
    roundedRect($img, 0, 0, $size - 1, $size - 1, $radius, $indigo);

    // Tag body: a square rotated 45° with one corner cut, drawn as a polygon.
    $s     = $maskable ? $size * 0.52 : $size * 0.62;   // smaller for safe zone
    $cx    = $size / 2;
    $cy    = $size / 2;
    $half  = $s / 2;
    $notch = $s * 0.34;

    $pts = [
        $cx - $half,          $cy - $half + $notch,
        $cx - $half + $notch, $cy - $half,
        $cx + $half,          $cy - $half,
        $cx + $half,          $cy + $half,
        $cx - $half,          $cy + $half,
    ];

    imagefilledpolygon($img, array_map('intval', $pts), $white);

    // The tag's punch hole, back in the background colour.
    $hole = max(4, (int) round($s * 0.14));
    imagefilledellipse(
        $img,
        (int) round($cx - $half + $notch * 0.85),
        (int) round($cy - $half + $notch * 0.85),
        $hole,
        $hole,
        $indigo
    );

    imagepng($img, $path);
    imagedestroy($img);

    printf("  %-44s %d bytes\n", basename($path), filesize($path));
}

makeIcon(192, "$out/icon-192.png");
makeIcon(512, "$out/icon-512.png");
makeIcon(512, "$out/icon-maskable-512.png", true);
makeIcon(180, "$out/apple-touch-icon.png");

echo "done\n";
