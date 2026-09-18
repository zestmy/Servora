<?php

/**
 * Generates every Servora brand asset from one set of numbers.
 *
 * The identity is drawn here rather than shipped as opaque binaries, for the
 * same reason the clock and label app icons are (see make-clock-app-icons.php):
 * a PNG nobody can regenerate is a PNG nobody can correct. Change a constant
 * in this file, re-run it, and the favicon, the PWA icons, the marketing
 * lockup and the reversed sidebar logo all move together.
 *
 *   php scripts/make-brand-assets.php
 *
 * ── The mark ────────────────────────────────────────────────────────────────
 * An angular S cut from a single folded ribbon: a top bar, a diagonal, a
 * bottom bar, all sharing two edge directions so nothing in the silhouette is
 * arbitrary. The geometry has 180° rotational symmetry, which is what keeps it
 * balanced at 16px where a lopsided glyph turns to mush.
 *
 * Three constants drive the whole shape, bound by 2L + T + D = 1 so the bottom
 * bar ends exactly as far from the left edge as the top bar starts from it —
 * break that identity and the symmetry goes with it.
 *
 * ── Why it is drawn at 4x and scaled down ───────────────────────────────────
 * GD has no anti-aliased polygon fill. imagefilledpolygon gives hard, jagged
 * diagonals, and this mark is nothing but diagonals. Everything is therefore
 * drawn at SUPERSAMPLE times the final size and resampled down once at the
 * end, which is where the smooth edges come from. Resample once, at the end:
 * compositing already-downsampled pieces stacks up soft edges.
 */

$root  = dirname(__DIR__);
$out   = $root . '/public/images';
$icons = $out . '/icons';
$fonts = $root . '/resources/fonts/outfit';

foreach ([$out, $icons] as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

const SUPERSAMPLE = 4;

/**
 * Mark geometry, in fractions of the mark's own bounding box.
 *
 *   T  bar thickness, and the horizontal thickness of the diagonal
 *   L  how far in from the left the top bar starts
 *   D  how far the diagonal drifts right over the mark's full height
 *
 * ASPECT is width ÷ height. Slightly wide: the bars need length to read as
 * bars rather than as wedges, and a square box starves them.
 */
const T      = 0.34;
const L      = 0.15;
const D      = 0.36;
const ASPECT = 1.15;

/** Brand colours. Mirrors the `brand` scale in tailwind.config.js. */
const BLUE_400 = '#60a5fa';
const BLUE_500 = '#3b82f6';
const BLUE_600 = '#2563eb';   // Primary Blue — the one colour of the two
const BLUE_700 = '#1d4ed8';
const NAVY     = '#0b1f3b';   // Deep Navy — wordmark, dark surfaces

/** Tagline and wordmark, in one place so a copy change is a one-line change. */
const WORDMARK = 'SERVORA';
const TAGLINE  = 'AI-Powered F&B Operations';

// ── Small helpers ───────────────────────────────────────────────────────────

/** '#2563eb' → [37, 99, 235] */
function rgb(string $hex): array
{
    return [
        (int) hexdec(substr($hex, 1, 2)),
        (int) hexdec(substr($hex, 3, 2)),
        (int) hexdec(substr($hex, 5, 2)),
    ];
}

/**
 * A transparent truecolour canvas with alpha preserved on save.
 *
 * $tint is the colour the transparency is made of. It is never seen directly,
 * but GD blends partially-covered pixels towards it, so type drawn on a canvas
 * tinted with its own ink colour keeps a clean edge.
 */
function canvas(int $w, int $h, string $tint = '#000000')
{
    [$r, $g, $b] = rgb($tint);

    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocatealpha($img, $r, $g, $b, 127));
    imagealphablending($img, true);

    return $img;
}

/** Flattens [[x, y], …] in 0..1 space to GD's flat pixel list. */
function points(array $pts, float $w, float $h, float $ox = 0, float $oy = 0): array
{
    $flat = [];

    foreach ($pts as [$x, $y]) {
        $flat[] = (int) round($ox + $x * $w);
        $flat[] = (int) round($oy + $y * $h);
    }

    return $flat;
}

/**
 * The two regions of the bounding box the mark does NOT occupy.
 *
 * The mark is drawn by filling its whole box and then clearing these, rather
 * than by filling the S itself: that way the fill can be a gradient laid down
 * in flat scanlines, which GD is good at, instead of a gradient clipped to a
 * concave polygon, which GD cannot do at all.
 */
function negativeSpace(): array
{
    $bandLeftAtBottom  = L + D * (1 - T);   // diagonal's left edge, where the bottom bar starts
    $bandRightAtTop    = L + T + D * T;     // diagonal's right edge, where the top bar ends
    $terminalOvershoot = D * T;             // how far the angled bar ends lean back

    return [
        [[0, 0], [L, 0], [$bandLeftAtBottom, 1 - T], [$terminalOvershoot, 1 - T], [0, 1]],
        [[1, 1], [1 - L, 1], [$bandRightAtTop, T], [1 - $terminalOvershoot, T], [1, 0]],
    ];
}

/** The diagonal band on its own — the ribbon's middle face. */
function foldFace(): array
{
    return [[L, 0], [L + T, 0], [L + T + D, 1], [L + D, 1]];
}

/**
 * The mark as its own transparent layer, sized in already-supersampled pixels.
 *
 * $fill is either 'gradient' (the full-colour mark) or a hex string (the
 * monochrome, reversed and white-on-blue versions, which are flat by
 * definition — a one-colour logo that carries a gradient is not one colour).
 */
function markLayer(int $w, int $h, string $fill = 'gradient')
{
    $img = canvas($w, $h);
    imagealphablending($img, false);

    if ($fill === 'gradient') {
        // Swept along the anti-diagonal so the light comes from the top left,
        // which is where every OS drop shadow assumes it comes from.
        [$r1, $g1, $b1] = rgb(BLUE_500);
        [$r2, $g2, $b2] = rgb(BLUE_700);
        $span = $w + $h;

        for ($i = 0; $i <= $span; $i++) {
            $t = $i / $span;
            imageline($img, $i, 0, 0, $i, imagecolorallocate(
                $img,
                (int) round($r1 + ($r2 - $r1) * $t),
                (int) round($g1 + ($g2 - $g1) * $t),
                (int) round($b1 + ($b2 - $b1) * $t),
            ));
        }

        // The fold. Barely there on purpose: at 26% the diagonal stops being a
        // face of the ribbon and becomes a stripe across the letter, and the S
        // splits into three shapes instead of reading as one.
        imagealphablending($img, true);
        [$nr, $ng, $nb] = rgb(NAVY);
        imagefilledpolygon($img, points(foldFace(), $w, $h), imagecolorallocatealpha($img, $nr, $ng, $nb, 113));
    } else {
        [$r, $g, $b] = rgb($fill);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, $r, $g, $b));
    }

    imagealphablending($img, false);
    $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);

    foreach (negativeSpace() as $region) {
        imagefilledpolygon($img, points($region, $w, $h), $clear);
    }

    imagealphablending($img, true);

    return $img;
}

/** Resamples a supersampled canvas down to its delivered size. */
function downsample($img, int $w, int $h)
{
    $out = imagecreatetruecolor($w, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));

    return $out;
}

/**
 * Clears the four corners outside a rounded rectangle.
 *
 * Same move as the mark's negative space, and for the same reason: the plate
 * underneath is a gradient, and rounding it by re-drawing arcs over it would
 * mean drawing arcs in a colour that changes across the arc. Clearing the
 * outside instead leaves the gradient untouched.
 *
 * The arc is approximated with a polygon. At 4x supersampling a 32-segment
 * quarter circle is well under half a delivered pixel off the true curve.
 */
function clearRoundedCorners($img, int $w, int $h, int $r, int $clear): void
{
    if ($r <= 0) {
        return;
    }

    $corners = [
        [[0, 0],       $r,      $r,      180, 270],
        [[$w, 0],      $w - $r, $r,      270, 360],
        [[$w, $h],     $w - $r, $h - $r, 0,   90],
        [[0, $h],      $r,      $h - $r, 90,  180],
    ];

    foreach ($corners as [$tip, $cx, $cy, $from, $to]) {
        $pts   = [$tip[0], $tip[1]];
        $steps = 32;

        for ($i = 0; $i <= $steps; $i++) {
            $a     = deg2rad($from + ($to - $from) * $i / $steps);
            $pts[] = (int) round($cx + $r * cos($a));
            $pts[] = (int) round($cy + $r * sin($a));
        }

        imagefilledpolygon($img, $pts, $clear);
    }
}

// ── Type ────────────────────────────────────────────────────────────────────

/**
 * How far the cursor moves after a glyph, in pixels.
 *
 * Not the same as the glyph's ink width, which is what imagettfbbox reports
 * for a lone character: a space has no ink at all and would measure zero,
 * closing up every word in the tagline. Measuring the character between two
 * sentinels and subtracting the sentinels on their own recovers the advance,
 * side bearings included.
 */
function advance(float $size, string $font, string $char): int
{
    static $cache = [];

    $key = $size . '|' . $font . '|' . $char;

    if (! isset($cache[$key])) {
        $with = imagettfbbox($size, 0, $font, '|' . $char . '|');
        $bare = imagettfbbox($size, 0, $font, '||');
        $cache[$key] = (int) round(($with[2] - $with[0]) - ($bare[2] - $bare[0]));
    }

    return $cache[$key];
}

/**
 * Draws text letter by letter so the lockup can carry tracking.
 *
 * imagettftext has no letter-spacing argument, and the wordmark needs it: set
 * solid, SERVORA reads as one dense block, and the tagline under it turns into
 * a grey smear at favicon-adjacent sizes.
 *
 * Returns the width of the line, so callers can measure before they commit.
 */
function drawTracked($img, float $size, int $x, int $y, int $colour, string $font, string $text, float $tracking, bool $draw = true): int
{
    $cursor = $x;
    $chars  = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($chars as $char) {
        if ($draw) {
            imagettftext($img, $size, 0, $cursor, $y, $colour, $font, $char);
        }

        $cursor += advance($size, $font, $char) + (int) round($size * $tracking);
    }

    // The trailing gap is spacing between letters, not part of the word.
    return (int) round($cursor - $x - $size * $tracking);
}

/** Ink height of a string at a given size — used to size type by cap height. */
function capHeight(float $size, string $font, string $text): int
{
    $box = imagettfbbox($size, 0, $font, $text);

    return (int) round(abs($box[7] - $box[1]));
}

/** Point size that renders $text at exactly $target pixels of cap height. */
function sizeForCapHeight(float $target, string $font, string $text): float
{
    $probe = 100.0;

    return $probe * $target / max(1, capHeight($probe, $font, $text));
}

// ── Lockups ─────────────────────────────────────────────────────────────────

/**
 * A logo lockup: the mark, the wordmark, and the tagline.
 *
 * $layout   'horizontal' (mark beside the type) or 'stacked' (mark above it)
 * $markFill 'gradient' or a hex — the mark's own colour
 * $ink      the wordmark colour
 * $muted    the tagline colour, which is deliberately not the wordmark's:
 *           a tagline at full contrast competes with the name above it
 */
function lockup(string $layout, string $markFill, string $ink, string $muted, int $markHeight, string $fontBold, string $fontRegular)
{
    $ss = SUPERSAMPLE;
    $mh = $markHeight * $ss;
    $mw = (int) round($mh * ASPECT);

    // Proportions are all expressed against the mark's height, so the lockup
    // rescales as one object.
    $wordCap  = (int) round($mh * ($layout === 'stacked' ? 0.40 : 0.44));
    $tagCap   = (int) round($wordCap * 0.30);
    $lineGap  = (int) round($wordCap * 0.34);
    $markGap  = (int) round($mh * 0.26);
    $pad      = (int) round($mh * 0.04);   // keeps anti-aliased tips off the edge

    $wordSize = sizeForCapHeight($wordCap, $fontBold, WORDMARK);
    $tagSize  = sizeForCapHeight($tagCap, $fontRegular, 'AIPO');   // caps only: descenders must not shrink the line

    $probe    = canvas(10, 10);
    $wordW    = drawTracked($probe, $wordSize, 0, 0, 0, $fontBold, WORDMARK, 0.055, false);
    $tagW     = drawTracked($probe, $tagSize, 0, 0, 0, $fontRegular, TAGLINE, 0.045, false);
    imagedestroy($probe);

    $typeW      = max($wordW, $tagW);
    $typeH      = $wordCap + $lineGap + $tagCap;
    $tagDescend = (int) round($tagSize * 0.22);   // room under the tagline's p / g

    if ($layout === 'stacked') {
        $w = max($mw, $typeW) + $pad * 2;
        $h = $mh + $markGap + $typeH + $tagDescend + $pad * 2;
        $markX = (int) round(($w - $mw) / 2);
        $markY = $pad;
        $typeY = $markY + $mh + $markGap;
    } else {
        $w = $mw + $markGap + $typeW + $pad * 2;
        $h = max($mh, $typeH + $tagDescend) + $pad * 2;
        $markX = $pad;
        $markY = (int) round(($h - $mh) / 2);
        $typeY = (int) round(($h - $typeH) / 2);
    }

    // Tinted transparent rather than transparent black: GD blends the
    // anti-aliased edge of every glyph against whatever is already in the
    // pixel, and a transparent BLACK canvas leaves a grey rim around white
    // type on the reversed logo.
    $img = canvas($w, $h, $ink);

    $mark = markLayer($mw, $mh, $markFill);
    imagealphablending($img, false);
    imagecopy($img, $mark, $markX, $markY, 0, 0, $mw, $mh);
    imagealphablending($img, true);
    imagedestroy($mark);

    [$ir, $ig, $ib] = rgb($ink);
    [$mr, $mg, $mb] = rgb($muted);
    $inkColour   = imagecolorallocate($img, $ir, $ig, $ib);
    $tagColour   = imagecolorallocate($img, $mr, $mg, $mb);

    $wordX = $layout === 'stacked' ? (int) round(($w - $wordW) / 2) : $markX + $mw + $markGap;
    $tagX  = $layout === 'stacked' ? (int) round(($w - $tagW) / 2) : $wordX;

    // imagettftext places text on its baseline, so cap height is added to the
    // top of each line rather than centring the glyph box — otherwise the two
    // lines drift apart as the tagline's descenders come and go.
    drawTracked($img, $wordSize, $wordX, $typeY + $wordCap, $inkColour, $fontBold, WORDMARK, 0.055);
    drawTracked($img, $tagSize, $tagX, $typeY + $wordCap + $lineGap + $tagCap, $tagColour, $fontRegular, TAGLINE, 0.045);

    $final = downsample($img, (int) round($w / $ss), (int) round($h / $ss));
    imagedestroy($img);

    return $final;
}

// ── Icons ───────────────────────────────────────────────────────────────────

/**
 * The app icon: the white mark on a blue rounded square.
 *
 * $maskable bleeds the background to the edges and shrinks the glyph into the
 * safe zone, because Android crops maskable icons to a shape we do not choose.
 */
function appIcon(int $size, bool $maskable = false)
{
    $ss = $size <= 64 ? 8 : SUPERSAMPLE;   // tiny icons need the extra sampling
    $s  = $size * $ss;

    $img = canvas($s, $s);

    // A flat-filled square would look dead next to the gradient mark it
    // carries, so the plate gets the same light-from-top-left sweep.
    [$r1, $g1, $b1] = rgb(BLUE_500);
    [$r2, $g2, $b2] = rgb(BLUE_700);
    imagealphablending($img, false);

    for ($i = 0; $i <= $s * 2; $i++) {
        $t = $i / ($s * 2);
        imageline($img, $i, 0, 0, $i, imagecolorallocate(
            $img,
            (int) round($r1 + ($r2 - $r1) * $t),
            (int) round($g1 + ($g2 - $g1) * $t),
            (int) round($b1 + ($b2 - $b1) * $t),
        ));
    }

    // Maskable icons are cropped to a shape the OS picks, so they must bleed
    // to the edges; everything else gets the squircle.
    if (! $maskable) {
        clearRoundedCorners($img, $s - 1, $s - 1, (int) round($s * 0.22), imagecolorallocatealpha($img, 0, 0, 0, 127));
    }

    imagealphablending($img, true);

    // Maskable art loses up to 20% off every edge to the OS mask.
    $glyphW = (int) round($s * ($maskable ? 0.46 : 0.60));
    $glyphH = (int) round($glyphW / ASPECT);
    $mark   = markLayer($glyphW, $glyphH, '#ffffff');
    imagecopy($img, $mark, (int) round(($s - $glyphW) / 2), (int) round(($s - $glyphH) / 2), 0, 0, $glyphW, $glyphH);
    imagedestroy($mark);

    $final = downsample($img, $size, $size);
    imagedestroy($img);

    return $final;
}

/** The bare mark on transparency, for surfaces that already carry the name. */
function bareMark(int $height, string $fill = 'gradient')
{
    $ss = SUPERSAMPLE;
    $h  = $height * $ss;
    $w  = (int) round($h * ASPECT);

    $layer = markLayer($w, $h, $fill);
    $final = downsample($layer, (int) round($w / $ss), $height);
    imagedestroy($layer);

    return $final;
}

/**
 * Packs PNGs into an .ico.
 *
 * GD cannot write .ico, and /favicon.ico is still fetched by browsers that
 * never look at the <link> tags, so the container is assembled by hand.
 * PNG-compressed entries are used rather than BMP: every browser that still
 * asks for favicon.ico understands them, and they are a third of the size.
 */
function writeIco(array $pngs, string $path): void
{
    $count  = count($pngs);
    $header = pack('vvv', 0, 1, $count);
    $offset = 6 + $count * 16;
    $dir    = '';
    $blobs  = '';

    foreach ($pngs as $size => $blob) {
        $dir .= pack('CCCCvvVV', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size, 0, 0, 1, 32, strlen($blob), $offset);
        $offset += strlen($blob);
        $blobs  .= $blob;
    }

    file_put_contents($path, $header . $dir . $blobs);
}

/** PNG bytes for an image, without touching disk. */
function pngBytes($img): string
{
    ob_start();
    imagepng($img);

    return (string) ob_get_clean();
}

// ── SVG master ──────────────────────────────────────────────────────────────

/**
 * The same geometry as SVG.
 *
 * Written from the constants above rather than drawn by hand, so the vector
 * and the rasters cannot drift apart. This is the file to hand a printer or a
 * sign maker; it is also what the app should use wherever an <img> can be
 * vector, since it stays sharp at any size for about a kilobyte.
 */
function markPoints(float $w, float $h): string
{
    $bandLeftAtBottom  = L + D * (1 - T);
    $bandRightAtTop    = L + T + D * T;
    $terminalOvershoot = D * T;

    // The S as one closed ring, walked clockwise from the top-right tip.
    $ring = [
        [1, 0], [L, 0], [$bandLeftAtBottom, 1 - T], [$terminalOvershoot, 1 - T],
        [0, 1], [1 - L, 1], [$bandRightAtTop, T], [1 - $terminalOvershoot, T],
    ];

    return implode(' ', array_map(
        static fn ($p) => round($p[0] * $w, 2) . ',' . round($p[1] * $h, 2),
        $ring,
    ));
}

/** The gradient both SVGs fill with. */
function markGradientDef(): string
{
    return '<linearGradient id="servora-fill" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="' . BLUE_500 . '"/>'
        . '<stop offset="1" stop-color="' . BLUE_700 . '"/>'
        . '</linearGradient>';
}

/** The bare mark, on transparency. */
function markSvg(): string
{
    $w    = round(100 * ASPECT, 2);
    $h    = 100.0;
    $s    = markPoints($w, $h);
    $fold = implode(' ', array_map(
        static fn ($p) => round($p[0] * $w, 2) . ',' . round($p[1] * $h, 2),
        foldFace(),
    ));

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Servora">'
        . '<defs>' . markGradientDef()
        . '<clipPath id="servora-clip"><polygon points="' . $s . '"/></clipPath></defs>'
        . '<polygon points="' . $s . '" fill="url(#servora-fill)"/>'
        . '<polygon points="' . $fold . '" fill="' . NAVY . '" fill-opacity="0.11" clip-path="url(#servora-clip)"/>'
        . '</svg>' . "\n";
}

/** The app icon: white mark on the blue squircle. */
function iconSvg(): string
{
    $size   = 100.0;
    $glyphW = round($size * 0.60, 2);
    $glyphH = round($glyphW / ASPECT, 2);
    $gx     = round(($size - $glyphW) / 2, 2);
    $gy     = round(($size - $glyphH) / 2, 2);

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="Servora">'
        . '<defs>' . markGradientDef() . '</defs>'
        . '<rect width="' . $size . '" height="' . $size . '" rx="' . round($size * 0.22, 2) . '" fill="url(#servora-fill)"/>'
        . '<g transform="translate(' . $gx . ' ' . $gy . ')">'
        . '<polygon points="' . markPoints($glyphW, $glyphH) . '" fill="#ffffff"/></g>'
        . '</svg>' . "\n";
}

// ── Write everything ────────────────────────────────────────────────────────

$fontBold    = $fonts . '/Outfit-Bold.ttf';
$fontRegular = $fonts . '/Outfit-Regular.ttf';

foreach ([$fontBold, $fontRegular] as $font) {
    if (! is_file($font)) {
        fwrite(STDERR, "Missing font: $font\n");
        exit(1);
    }
}

$written = [];

$write = function (string $path, $img) use (&$written): void {
    imagepng($img, $path);
    imagedestroy($img);
    $written[] = str_replace(dirname(__DIR__) . '/', '', $path) . ' (' . number_format(filesize($path) / 1024, 1) . ' KB)';
};

// Primary lockup, for light backgrounds. Keeps its existing filename: every
// layout in the app already points at it, and the names describe the wordmark
// colour, which has not changed polarity.
$write($out . '/servora-logo-black.png', lockup('horizontal', 'gradient', NAVY, '#475569', 240, $fontBold, $fontRegular));

// Reversed, for the dark sidebar and the marketing footer.
$write($out . '/servora-logo-white.png', lockup('horizontal', 'gradient', '#ffffff', '#cbd5e1', 240, $fontBold, $fontRegular));

// One-colour, for faxes, etched glass, single-plate print and anywhere the
// gradient cannot survive.
$write($out . '/servora-logo-mono.png', lockup('horizontal', NAVY, NAVY, NAVY, 240, $fontBold, $fontRegular));
$write($out . '/servora-logo-mono-white.png', lockup('horizontal', '#ffffff', '#ffffff', '#ffffff', 240, $fontBold, $fontRegular));

// Stacked, for square-ish spaces: social avatars with room for type, invoice
// headers, the login card.
$write($out . '/servora-logo-stacked.png', lockup('stacked', 'gradient', NAVY, '#475569', 240, $fontBold, $fontRegular));
$write($out . '/servora-logo-stacked-white.png', lockup('stacked', 'gradient', '#ffffff', '#cbd5e1', 240, $fontBold, $fontRegular));

// The mark alone, no plate, on transparency.
$write($out . '/servora-mark.png', bareMark(512));
$write($out . '/servora-mark-white.png', bareMark(512, '#ffffff'));

// The app icon at every size the reference sheet calls for. 1024 is the store
// upload, 512 the PWA, 256/128 the desktop, 64/32/16 the browser chrome.
foreach ([1024, 512, 256, 128, 64, 32, 16] as $size) {
    $write($icons . "/servora-icon-{$size}.png", appIcon($size));
}

// Existing filenames, kept pointing at the same artwork.
$write($out . '/servora-icon.png', appIcon(512));
$write($root . '/public/favicon.png', appIcon(512));
$write($root . '/public/servora-maskable-512.png', appIcon(512, true));

writeIco(
    [16 => pngBytes(appIcon(16)), 32 => pngBytes(appIcon(32)), 48 => pngBytes(appIcon(48))],
    $root . '/public/favicon.ico',
);
$written[] = 'public/favicon.ico (' . number_format(filesize($root . '/public/favicon.ico') / 1024, 1) . ' KB)';

file_put_contents($out . '/servora-mark.svg', markSvg());
$written[] = 'public/images/servora-mark.svg';
file_put_contents($out . '/servora-icon.svg', iconSvg());
$written[] = 'public/images/servora-icon.svg';

echo "Servora brand assets written:\n";

foreach ($written as $line) {
    echo "  $line\n";
}
