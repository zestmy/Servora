<?php

namespace App\Support;

/**
 * Makes text safe for a PDF font that has never heard of emoji.
 *
 * REPORTED AS question marks all over the AI Analysis PDF — "? Highlights",
 * "Performance: ? Average", "? vs same 11 days last month". Two causes, both
 * fixed together:
 *
 *   1. The PDF layout asked for Helvetica. In dompdf that is one of the
 *      fourteen built-in PDF core fonts, and its encoding is Latin-1 — an
 *      arrow, a check mark or a dash outside that set renders as "?". The
 *      layout now asks for DejaVu Sans, which dompdf bundles and which covers
 *      arrows, dashes, bullets and geometric shapes.
 *
 *   2. DejaVu still has no emoji, and the model writes them naturally into
 *      headings and notes. So the common ones are mapped to glyphs DejaVu DOES
 *      have — an up arrow stays an up arrow — and anything left is dropped
 *      rather than printed as a question mark.
 *
 * The prompt also now asks for no emoji, which stops this at source. This stays
 * because analyses already saved contain them, and because a model instruction
 * is a request rather than a guarantee.
 */
class PdfSafeText
{
    /**
     * Emoji that carry meaning here, mapped to something DejaVu can draw.
     *
     * Kept small on purpose: these are the ones an analysis actually uses.
     * Everything else is noise in a printed report and is removed.
     */
    private const REPLACEMENTS = [
        '📈' => '▲', '📉' => '▼', '⬆' => '▲', '⬇' => '▼',
        '⬆️' => '▲', '⬇️' => '▼', '↗️' => '▲', '↘️' => '▼',
        '✅' => '✓', '☑️' => '✓', '✔️' => '✓',
        '❌' => '✗', '❎' => '✗',
        '⚠️' => '!', '⚠' => '!',
        '🔴' => '●', '🟠' => '●', '🟡' => '●', '🟢' => '●', '🔵' => '●',
        '•' => '•',
    ];

    public static function clean(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = strtr($text, self::REPLACEMENTS);

        /*
         * Strip what is left of the emoji planes.
         *
         * Deliberately NOT a blanket "non-ASCII" strip: this product is
         * Malaysian and its reports carry Malay text, ringgit figures and en
         * dashes, all of which DejaVu draws correctly and all of which would be
         * destroyed by a stricter filter.
         */
        $ranges = '/[\x{1F000}-\x{1FAFF}'   // emoji, symbols and pictographs
            . '\x{FE00}-\x{FE0F}'            // variation selectors
            . '\x{1F1E6}-\x{1F1FF}'          // regional indicators (flags)
            . '\x{200D}]/u';                 // zero-width joiner

        /*
         * Arrows (U+2190–U+21FF) are deliberately NOT stripped. DejaVu draws
         * them, the analysis uses them to mean something, and removing the
         * arrow from "revenue ↑ 4.1%" takes the sentence with it. They only
         * looked broken because of the font, which is the actual fix.
         */

        return trim(preg_replace($ranges, '', $text) ?? '');
    }
}
