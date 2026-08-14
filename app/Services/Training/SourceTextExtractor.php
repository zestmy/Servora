<?php

namespace App\Services\Training;

use App\Models\Recipe;
use App\Services\VisionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Turn whatever the author dropped in into plain prose.
 *
 * Everything downstream wants text: the question writer reads it, the course
 * page renders it, and the search box searches it. So the conversion happens
 * once, here, at import — not lazily on every read, and not per consumer.
 *
 * WHY EXTRACTION FAILURE IS NOT AN EXCEPTION. A scanned PDF — a photographed
 * policy sheet, which is a very normal thing for a restaurant to have — parses
 * to zero characters without erroring. Throwing would tell the author their
 * upload was broken when it is merely an image; returning empty lets the caller
 * say the useful thing instead ("we could not read any text out of that — paste
 * it in and we will still write the quiz").
 */
class SourceTextExtractor
{
    /** Beyond this the AI prompt is trimmed; see QuizGeneratorService. */
    public const MAX_CHARS = 60000;

    /**
     * An SOP as the trainee reads it: what it is, then how it is made.
     *
     * Deliberately the same content the SOP PDF carries, in the same order, so
     * a course generated from a recipe and the printed SOP on the wall cannot
     * teach two different methods.
     */
    public function fromRecipe(Recipe $recipe): string
    {
        $recipe->loadMissing(['steps', 'lines.ingredient', 'lines.uom', 'yieldUom']);

        $parts = [];

        $parts[] = '# ' . $recipe->name;

        if (filled($recipe->description)) {
            $parts[] = trim($recipe->description);
        }

        $facts = [];
        if ($recipe->category) {
            $facts[] = 'Category: ' . $recipe->category;
        }
        if ($recipe->yield_quantity) {
            $facts[] = 'Yield: ' . rtrim(rtrim((string) $recipe->yield_quantity, '0'), '.')
                . ' ' . ($recipe->yieldUom->abbreviation ?? $recipe->yieldUom->name ?? '');
        }
        if ($recipe->shelf_life_value) {
            $facts[] = 'Shelf life: ' . rtrim(rtrim((string) $recipe->shelf_life_value, '0'), '.')
                . ' ' . ($recipe->shelf_life_unit ?? '');
        }
        if ($recipe->storage_instruction) {
            $facts[] = 'Storage: ' . (Recipe::STORAGE_OPTIONS[$recipe->storage_instruction] ?? $recipe->storage_instruction);
        }
        if ($facts) {
            $parts[] = implode("\n", $facts);
        }

        $lines = $recipe->lines->filter(fn ($l) => $l->ingredient !== null);
        if ($lines->isNotEmpty()) {
            $parts[] = "## Ingredients\n" . $lines
                ->map(function ($line) {
                    $qty = rtrim(rtrim((string) $line->quantity, '0'), '.');
                    $uom = $line->uom->abbreviation ?? $line->uom->name ?? '';

                    return trim("- {$qty} {$uom} {$line->ingredient->name}");
                })
                ->implode("\n");
        }

        if ($recipe->steps->isNotEmpty()) {
            $parts[] = "## Method\n" . $recipe->steps
                ->sortBy('sort_order')
                ->values()
                ->map(function ($step, $i) {
                    $head = $step->title ? $step->title . ' — ' : '';

                    return ($i + 1) . '. ' . $head . trim((string) $step->instruction);
                })
                ->implode("\n");
        }

        return $this->tidy(implode("\n\n", array_filter($parts)));
    }

    /**
     * Below this many characters, a PDF is treated as having no text layer.
     *
     * Not zero. A scanned page routinely yields a handful of stray characters —
     * a page number the scanner's own software stamped on, a bit of metadata
     * that leaked into the content stream — and testing for exactly nothing
     * would send those documents down the "we could not read it" path with two
     * useless characters in hand. 200 is comfortably below any real page of
     * prose and comfortably above that noise.
     */
    private const TEXT_LAYER_FLOOR = 200;

    /**
     * How many pages of a scan are worth reading.
     *
     * Every page is a separate vision call: real money and real seconds, on a
     * box with five php-fpm workers. Ten pages covers the SOPs and policy
     * sheets this is for; a hundred-page manual is a different job and should
     * not silently become a two-minute request that blocks a worker.
     */
    private const MAX_SCANNED_PAGES = 10;

    public function __construct(private ?VisionService $vision = null)
    {
    }

    /**
     * Text out of an uploaded document.
     *
     * PDF, plain text and markdown are read directly. A .docx is a zip of XML,
     * and pulling the body text out of `word/document.xml` avoids adding a
     * dependency for what is three lines of tag-stripping — the formatting is
     * being discarded anyway.
     */
    public function fromUpload(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            $text = match ($extension) {
                'pdf'          => $this->fromPdf($file->getRealPath()),
                'docx'         => $this->fromDocx($file->getRealPath()),
                'txt', 'md'    => (string) file_get_contents($file->getRealPath()),
                default        => '',
            };
        } catch (\Throwable $e) {
            // See the class note: an unreadable document is a normal outcome,
            // not an error the author can act on.
            Log::warning('[training] could not extract text from upload', [
                'name'  => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            $text = '';
        }

        $text = $this->tidy($text);

        /*
         * A PDF with no text layer is READ WITH VISION rather than refused.
         *
         * This is the common case for the documents this feature is actually
         * pointed at: a policy sheet photographed on a phone, or a supplier's
         * fact sheet scanned to PDF. There is no text in the file at all —
         * pdfparser is working correctly and returning nothing — so the honest
         * fallback is to look at the pages rather than to read them.
         *
         * Text layer first, always, and not merely as an optimisation: where
         * one exists it is EXACT, free and instant, where vision is a paid
         * approximation that can misread a temperature. Falling back only when
         * there is nothing to read keeps the accurate path the default.
         */
        if ($extension === 'pdf' && mb_strlen($text) < self::TEXT_LAYER_FLOOR) {
            $scanned = $this->readScannedPdf($file->getRealPath());

            if ($scanned !== '') {
                return $this->tidy($scanned);
            }
        }

        return $text;
    }

    /**
     * Read a PDF that has no text layer, one rasterised page at a time.
     *
     * Ghostscript and Imagick are both on the production box (the label and
     * Z-report features already lean on them), but neither is guaranteed in
     * every environment, so a missing extension returns empty and the caller
     * shows the same "paste it in instead" note it always did.
     *
     * Pages are converted and released ONE AT A TIME. Reading a whole PDF into
     * Imagick at once holds every page as an uncompressed bitmap, which is how
     * a ten-page scan turns into hundreds of megabytes on a 2 GB box with five
     * workers. 150 dpi is enough for a vision model to read body text without
     * making each frame larger than it needs to be.
     */
    public function readScannedPdf(string $path): string
    {
        if (! class_exists(\Imagick::class) || ! $this->vision) {
            return '';
        }

        try {
            $probe = new \Imagick();
            $probe->pingImage($path);
            $pages = min($probe->getNumberImages(), self::MAX_SCANNED_PAGES);
            $probe->clear();
        } catch (\Throwable $e) {
            Log::warning('[training] could not open PDF for rasterising', ['error' => $e->getMessage()]);

            return '';
        }

        $out = [];

        for ($i = 0; $i < $pages; $i++) {
            $frame = null;
            $temp  = null;

            try {
                $frame = new \Imagick();
                $frame->setResolution(150, 150);
                $frame->readImage($path . '[' . $i . ']');
                // Scans are commonly transparent-backed; flattening onto white
                // stops the text arriving as black-on-black.
                $frame->setImageBackgroundColor('white');
                $frame = $frame->flattenImages();
                $frame->setImageFormat('jpeg');
                $frame->setImageCompressionQuality(80);

                $temp = tempnam(sys_get_temp_dir(), 'sop') . '.jpg';
                $frame->writeImage($temp);

                $out[] = $this->vision->extractText($temp);
            } catch (\Throwable $e) {
                // One unreadable page does not make the document unreadable.
                Log::warning('[training] vision failed on a PDF page', [
                    'page'  => $i + 1,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                $frame?->clear();

                if ($temp && file_exists($temp)) {
                    unlink($temp);
                }
            }
        }

        return trim(implode("\n\n", array_filter($out)));
    }

    private function fromPdf(string $path): string
    {
        return (new PdfParser())->parseFile($path)->getText();
    }

    private function fromDocx(string $path): string
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        // Paragraph and line breaks become newlines before the tags go, or the
        // whole document arrives as one unreadable paragraph.
        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<w:br[^>]*\/>/', "\n", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * Collapse the whitespace a PDF extraction leaves behind.
     *
     * Runs of blank lines become one, trailing spaces go, and the result is cut
     * to MAX_CHARS. The cut is at a paragraph boundary where there is one
     * nearby, because a prompt that ends mid-sentence produces a question about
     * the half-sentence.
     */
    public function tidy(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]*\n/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) <= self::MAX_CHARS) {
            return $text;
        }

        $cut  = mb_substr($text, 0, self::MAX_CHARS);
        $stop = mb_strrpos($cut, "\n\n");

        return $stop !== false && $stop > self::MAX_CHARS * 0.8 ? mb_substr($cut, 0, $stop) : $cut;
    }
}
