<?php

namespace Tests\Unit;

use App\Services\Labels\PdfRotate;
use Barryvdh\DomPDF\Facade\Pdf;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The /Rotate spike, verified against real dompdf output — the one piece of
 * the agent path with no existing precedent (docs/print-agent-plan.md).
 *
 * The stakes: inserting text into a PDF shifts every byte after it, which
 * breaks the cross-reference table. Viewers repair that silently; print
 * pipelines are where the generosity ends. So these tests assert the xref
 * is REBUILT correctly, not just that the rotation text appears.
 */
class PdfRotateTest extends TestCase
{
    #[DataProvider('pageCounts')]
    public function test_rotates_every_page_and_rebuilds_a_valid_xref(int $pages): void
    {
        $pdf = $this->dompdfDocument($pages);

        $rotated = PdfRotate::rotate90($pdf);

        $this->assertNotSame($pdf, $rotated);

        // One /Rotate per page object, never on the /Pages tree node.
        $this->assertSame($pages, preg_match_all('~/Type /Page /Rotate 90~', $rotated));
        $this->assertDoesNotMatchRegularExpression('~/Type\s*/Pages\s*/Rotate~', $rotated);

        $this->assertXrefOffsetsAreExact($rotated);
    }

    public static function pageCounts(): array
    {
        // Multi-page matters: copies render as repeated pages, so a
        // 30-label run is a 30-page document.
        return ['one page' => [1], 'three pages' => [3]];
    }

    public function test_something_that_is_not_dompdf_output_passes_through_unchanged(): void
    {
        $notAPdf = "hello, I am not a PDF at all";

        $this->assertSame($notAPdf, PdfRotate::rotate90($notAPdf));
    }

    public function test_a_pdf_whose_xref_cannot_be_rebuilt_is_returned_unrotated(): void
    {
        // Has a page marker but no xref section: the rebuild must refuse,
        // and an UN-ROTATED but valid document beats a corrupt one.
        $mangled = "%PDF-1.7\n1 0 obj\n<< /Type /Page >>\nendobj\n%%EOF";

        $this->assertSame($mangled, PdfRotate::rotate90($mangled));
    }

    /**
     * Walk the rebuilt xref and require every entry to point at exactly
     * "N 0 obj" — byte-exact, which is the entire reason rebuildXref exists.
     */
    private function assertXrefOffsetsAreExact(string $pdf): void
    {
        $startxref = null;

        if (preg_match('~startxref\s+(\d+)\s*%%EOF~', $pdf, $m)) {
            $startxref = (int) $m[1];
        }

        $this->assertNotNull($startxref, 'startxref pointer missing');
        $this->assertSame('xref', substr($pdf, $startxref, 4), 'startxref does not point at the xref table');

        $this->assertSame(1, preg_match('~xref\s+0 (\d+)\s+(.*?)trailer~s', substr($pdf, $startxref), $m), 'xref table unreadable');

        $size    = (int) $m[1];
        $entries = preg_split('~\r?\n~', trim($m[2]));

        $this->assertCount($size, $entries, 'xref entry count disagrees with its own header');

        foreach ($entries as $number => $entry) {
            if ($number === 0) {
                continue; // the free-list head
            }

            $offset = (int) substr($entry, 0, 10);

            $this->assertSame(
                "{$number} 0 obj",
                substr($pdf, $offset, strlen("{$number} 0 obj")),
                "xref entry for object {$number} points at the wrong bytes"
            );
        }

        // And /Size in the trailer agrees with the table.
        $this->assertSame(1, preg_match('~/Size\s+(\d+)~', substr($pdf, $startxref), $sm));
        $this->assertSame($size, (int) $sm[1]);
    }

    private function dompdfDocument(int $pages): string
    {
        $html = '';

        for ($i = 0; $i < $pages; $i++) {
            $style = $i > 0 ? 'page-break-before: always;' : '';
            $html .= "<div style=\"{$style}\">Label {$i}</div>";
        }

        return Pdf::loadHTML($html)
            ->setPaper([0, 0, 198.42, 113.38]) // 70 × 40 mm in points
            ->output();
    }
}
