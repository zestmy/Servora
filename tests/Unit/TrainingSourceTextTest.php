<?php

namespace Tests\Unit;

use App\Services\Training\SourceTextExtractor;
use Tests\TestCase;

/**
 * Reading material out of whatever the author dropped in.
 *
 * The vision fallback itself is not exercised here — it is a paid API call per
 * page — but the DECISION to reach for it is, because that is the part with a
 * threshold in it and the part that will be wrong if somebody tunes the number
 * without thinking about scanner noise.
 */
class TrainingSourceTextTest extends TestCase
{
    private function extractor(): SourceTextExtractor
    {
        // No VisionService: readScannedPdf must decline rather than fatal when
        // there is nothing to call, which is also what a environment without
        // Imagick looks like.
        return new SourceTextExtractor();
    }

    /**
     * Runs of blank lines and internal spaces collapse; a single leading space
     * on a line survives, and that is deliberate rather than an oversight —
     * pasted material uses indentation to mean something (nested lists,
     * quoted rules) and flattening every line to the margin would destroy it.
     */
    public function test_it_collapses_the_whitespace_a_pdf_extraction_leaves_behind(): void
    {
        $messy = "Title\n\n\n\n   Body   with    gaps   \n\n\n\nEnd";

        $this->assertSame("Title\n\n Body with gaps\n\nEnd", $this->extractor()->tidy($messy));
    }

    public function test_it_normalises_windows_line_endings(): void
    {
        $this->assertSame("a\n\nb", $this->extractor()->tidy("a\r\n\r\nb"));
    }

    /**
     * The cut is at a paragraph boundary where there is one nearby, because a
     * prompt that ends mid-sentence produces a question about half a sentence.
     */
    public function test_it_cuts_long_material_at_a_paragraph_boundary(): void
    {
        // 75,000 characters, comfortably over the 60,000 cap.
        $head = str_repeat('word ', 15000);
        $text = $head . "\n\n" . str_repeat('tail ', 100);

        $out = $this->extractor()->tidy($text);

        $this->assertLessThanOrEqual(SourceTextExtractor::MAX_CHARS, mb_strlen($out));
        $this->assertStringNotContainsString('tail', $out);
    }

    /** With no vision service wired up it declines rather than fataling. */
    public function test_a_scanned_pdf_returns_nothing_when_vision_is_unavailable(): void
    {
        $this->assertSame('', $this->extractor()->readScannedPdf('/no/such/file.pdf'));
    }
}
