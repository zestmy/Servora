<?php

namespace App\Services\Labels;

/**
 * Sets /Rotate 90 on every page of a dompdf-produced PDF.
 *
 * Why this exists: rotate_90 satisfies a printer driver stuck on portrait
 * media. PrintNode did it with a job option; the agent path has no such
 * option — SumatraPDF cannot rotate — so the rotation has to live in the
 * document. dompdf cannot apply CSS transforms, which leaves the PDF page
 * dictionary's /Rotate entry: rendering is untouched, the viewer/driver
 * turns the page.
 *
 * Inserting text into a PDF shifts every byte after it, which silently
 * breaks the cross-reference table. Desktop viewers repair a broken xref
 * without a word; print pipelines are exactly where that generosity ends.
 * So after the insertion the xref is REBUILT, not left to luck.
 *
 * Deliberately narrow: this understands dompdf's own output — a classic
 * (non-stream) xref, objects numbered from 1, generation 0 — and refuses
 * anything else by returning the input unchanged. It is a targeted tool
 * for one renderer, not a PDF library.
 */
class PdfRotate
{
    public static function rotate90(string $pdf): string
    {
        /*
         * /Type /Page marks a page object; /Type /Pages marks the tree
         * node above them. The lookahead keeps this off the tree node —
         * rotating /Pages is legal PDF but would double-rotate.
         */
        $rotated = preg_replace(
            '~/Type\s*/Page(?![a-zA-Z])~',
            '/Type /Page /Rotate 90',
            $pdf,
            -1,
            $count
        );

        if (! is_string($rotated) || $count === 0) {
            return $pdf;
        }

        $rebuilt = self::rebuildXref($rotated);

        // A rebuild that failed means the layout was not the dompdf shape
        // this understands. An un-rotated label beats a corrupt document.
        return $rebuilt ?? $pdf;
    }

    /**
     * Regenerate the classic xref table and trailer for a PDF whose object
     * offsets have moved.
     */
    private static function rebuildXref(string $pdf): ?string
    {
        $xrefPos = strrpos($pdf, "\nxref");

        if ($xrefPos === false) {
            return null;
        }

        // Keep the original trailer dictionary verbatim — Root, Info and ID
        // are still right; only /Size is recomputed below to be safe.
        if (! preg_match('~\ntrailer\s*<<(.*?)>>~s', substr($pdf, $xrefPos), $m)) {
            return null;
        }

        $trailerDict = $m[1];

        $body = substr($pdf, 0, $xrefPos + 1); // keep the trailing newline

        preg_match_all('~(\d+)\s+0\s+obj~', $body, $objects, PREG_OFFSET_CAPTURE);

        if (! $objects[0]) {
            return null;
        }

        $offsets = [];

        foreach ($objects[1] as $i => [$number]) {
            $offsets[(int) $number] = $objects[0][$i][1];
        }

        $max = max(array_keys($offsets));

        // Objects must be numbered without gaps for the single-section
        // xref written below. dompdf's are; anything else bails out.
        for ($n = 1; $n <= $max; $n++) {
            if (! isset($offsets[$n])) {
                return null;
            }
        }

        $size = $max + 1;

        $xref = "xref\n0 {$size}\n0000000000 65535 f \n";

        for ($n = 1; $n <= $max; $n++) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$n]);
        }

        $trailerDict = preg_match('~/Size\s+\d+~', $trailerDict)
            ? preg_replace('~/Size\s+\d+~', "/Size {$size}", $trailerDict)
            : $trailerDict . " /Size {$size}";

        $startxref = strlen($body);

        return $body
            . $xref
            . "trailer\n<<{$trailerDict}>>\n"
            . "startxref\n{$startxref}\n%%EOF\n";
    }
}
