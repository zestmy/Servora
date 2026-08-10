<?php

namespace Tests\Feature;

use App\Support\PdfSafeText;
use Tests\TestCase;

/**
 * Question marks all over the AI Analysis PDF.
 *
 * Two causes. The layout asked for Helvetica, which in dompdf is a built-in
 * core font with Latin-1 encoding, so anything outside that set printed as "?".
 * And the model writes emoji into headings, which no print font here can draw.
 *
 * The font is fixed in the layout; this covers the second half, and in
 * particular that the cure is not worse than the disease.
 */
class PdfSafeTextTest extends TestCase
{
    public function test_it_keeps_meaning_by_mapping_rather_than_deleting(): void
    {
        $this->assertSame('▲ Revenue up', PdfSafeText::clean('📈 Revenue up'));
        $this->assertSame('✓ On target', PdfSafeText::clean('✅ On target'));
        $this->assertSame('! Watch this', PdfSafeText::clean('⚠️ Watch this'));
    }

    public function test_it_removes_what_it_cannot_map(): void
    {
        $this->assertSame('Great month', PdfSafeText::clean('🎉 Great month'));
        $this->assertSame('Team did well', PdfSafeText::clean('Team did well 👏🏽'));
    }

    /**
     * The cure must not eat the report. This product is Malaysian: its analyses
     * carry Malay text, ringgit figures, en dashes and arrows, and a blanket
     * non-ASCII filter would destroy all of them.
     */
    public function test_it_leaves_everything_a_report_actually_needs(): void
    {
        $kept = 'Jualan naik RM 7,610 (+4.1%) — pendapatan ↑ berbanding Julai';

        $this->assertSame($kept, PdfSafeText::clean($kept));
    }

    public function test_it_survives_nothing_at_all(): void
    {
        $this->assertSame('', PdfSafeText::clean(null));
        $this->assertSame('', PdfSafeText::clean(''));
    }
}
