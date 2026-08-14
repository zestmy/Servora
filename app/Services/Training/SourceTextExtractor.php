<?php

namespace App\Services\Training;

use App\Models\Recipe;
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

            return '';
        }

        return $this->tidy($text);
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
