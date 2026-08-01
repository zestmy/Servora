<?php

namespace App\Services\Labels;

/**
 * Character advance widths for Helvetica and Helvetica-Bold.
 *
 * Both renderers set the label in Helvetica (dompdf's default sans-serif and
 * the browser's), and the label renderer has to know how wide a string will
 * be BEFORE it lays it out — it shrinks a field's font until the text fits
 * its box, and nothing in PHP measures text for it.
 *
 * The previous approach used one average width for every character. On a
 * date that is nearly all digits — narrow at 0.556 em against an assumed
 * 0.62 — it overestimated by more than a tenth, which shrank the use-by
 * several points below what actually fitted. The use-by is the one thing
 * read across a chiller, so those points are worth a lookup table.
 *
 * Values are Adobe's published AFM widths, in thousandths of an em. Only
 * printable ASCII is listed: anything else falls back to the average, which
 * is what the whole table used to be.
 */
class HelveticaMetrics
{
    /** Fallback for characters outside the table (accents, symbols, CJK). */
    private const FALLBACK = 600;

    private const REGULAR = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889,
        '&' => 667, "'" => 191, '(' => 333, ')' => 333, '*' => 389, '+' => 584,
        ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556,
        '5' => 556, '6' => 556, '7' => 556, '8' => 556, '9' => 556,
        ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015,
        'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611,
        'G' => 778, 'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556,
        'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667, 'Q' => 778, 'R' => 722,
        'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
        'Y' => 667, 'Z' => 611,
        '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333,
        'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278,
        'g' => 556, 'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222,
        'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333,
        's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500,
        'y' => 500, 'z' => 500,
        '{' => 334, '|' => 260, '}' => 334, '~' => 584,
    ];

    private const BOLD = [
        ' ' => 278, '!' => 333, '"' => 474, '#' => 556, '$' => 556, '%' => 889,
        '&' => 722, "'" => 238, '(' => 333, ')' => 333, '*' => 389, '+' => 584,
        ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556,
        '5' => 556, '6' => 556, '7' => 556, '8' => 556, '9' => 556,
        ':' => 333, ';' => 333, '<' => 584, '=' => 584, '>' => 584, '?' => 611, '@' => 975,
        'A' => 722, 'B' => 722, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611,
        'G' => 778, 'H' => 722, 'I' => 278, 'J' => 556, 'K' => 722, 'L' => 611,
        'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667, 'Q' => 778, 'R' => 722,
        'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
        'Y' => 667, 'Z' => 611,
        '[' => 333, '\\' => 278, ']' => 333, '^' => 584, '_' => 556, '`' => 333,
        'a' => 556, 'b' => 611, 'c' => 556, 'd' => 611, 'e' => 556, 'f' => 333,
        'g' => 611, 'h' => 611, 'i' => 278, 'j' => 278, 'k' => 556, 'l' => 278,
        'm' => 889, 'n' => 611, 'o' => 611, 'p' => 611, 'q' => 611, 'r' => 389,
        's' => 556, 't' => 333, 'u' => 611, 'v' => 556, 'w' => 778, 'x' => 556,
        'y' => 556, 'z' => 500,
        '{' => 389, '|' => 280, '}' => 389, '~' => 584,
    ];

    /** Width of a string in em units — multiply by the font size for points. */
    public static function width(string $text, bool $bold = false): float
    {
        $table = $bold ? self::BOLD : self::REGULAR;
        $total = 0;

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $total += $table[$char] ?? self::FALLBACK;
        }

        return $total / 1000;
    }

    /**
     * How many lines the text needs at this size in this width.
     *
     * Greedy word wrap, matching how both renderers break a line: a word
     * moves whole to the next line unless it cannot fit on a line at all, in
     * which case it is left to overflow rather than pretending it broke —
     * being wrong in the direction of "needs more room" is the safe error
     * here, since the caller responds by shrinking the font.
     */
    public static function lineCount(string $text, bool $bold, float $fontSizePt, float $widthPt): int
    {
        if ($widthPt <= 0 || trim($text) === '') {
            return 1;
        }

        $spaceWidth = self::width(' ', $bold) * $fontSizePt;
        $lines      = 1;
        $current    = 0.0;

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            $wordWidth = self::width($word, $bold) * $fontSizePt;
            $needed    = $current > 0 ? $current + $spaceWidth + $wordWidth : $wordWidth;

            if ($needed <= $widthPt) {
                $current = $needed;

                continue;
            }

            $lines++;
            $current = $wordWidth;
        }

        return $lines;
    }
}
