<?php

namespace App\Services;

use App\Models\LabelTemplate;
use App\Services\Labels\HelveticaMetrics;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * Turns resolved label data into a printable document.
 *
 * Two output paths, ONE Blade view:
 *
 *   html() — what the browser driver prints and what the designer previews
 *   pdf()  — archive, reprint, and any future PrintNode driver
 *
 * Both render resources/views/labels/document.blade.php, so a preview is
 * always what actually prints. The price is that the view must stay inside
 * dompdf's CSS subset: absolute positioning and basic fonts only. No flexbox,
 * no grid, no transforms. Anything outside that subset renders in Chrome and
 * silently disappears from the PDF.
 *
 * This service does LAYOUT ONLY. Callers resolve token values (item name,
 * dates, staff) and hand them in; nothing here touches the database.
 */
class LabelRenderService
{
    /** Points per millimetre — dompdf wants a paper size in points. */
    private const PT_PER_MM = 2.834645669;

    private const MM_PER_PT = 0.352777778;

    /** Below this, thermal output at 203dpi stops being readable anyway. */
    private const MIN_FONT_PT = 5.0;

    /**
     * @param  array<int, array{template: LabelTemplate, values: array<string, string>, copies?: int}>  $labels
     * @param  float  $offsetXMm  Shift every field right, correcting a clipped left edge
     * @param  float  $offsetYMm  Shift every field down, correcting a clipped top edge
     */
    public function html(
        array $labels,
        float $widthMm,
        float $heightMm,
        float $offsetXMm = 0,
        float $offsetYMm = 0,
        bool $rotate = false
    ): string {
        return View::make('labels.document', [
            'pages'    => $this->pages($labels, $offsetXMm, $offsetYMm),
            'widthMm'  => $widthMm,
            'heightMm' => $heightMm,
            'rotate'   => $rotate,
        ])->render();
    }

    /**
     * Archive / reprint output.
     *
     * Deliberately takes no rotate flag. Rotation exists to satisfy a
     * printer driver stuck on portrait media; an archived label should read
     * the natural way up. dompdf also cannot apply CSS transforms, so a
     * rotated document would render with the label shifted off the page
     * rather than turned — passing the flag here would produce silent
     * corruption instead of a rotated PDF.
     *
     * @param  array<int, array{template: LabelTemplate, values: array<string, string>, copies?: int}>  $labels
     */
    public function pdf(
        array $labels,
        float $widthMm,
        float $heightMm,
        float $offsetXMm = 0,
        float $offsetYMm = 0
    ): string {
        $paper = [0, 0, $widthMm * self::PT_PER_MM, $heightMm * self::PT_PER_MM];

        return Pdf::loadHTML(
            $this->html($labels, $widthMm, $heightMm, $offsetXMm, $offsetYMm, false)
        )->setPaper($paper)->output();
    }

    /**
     * The calibration label: a ruler for measuring what a printer clips.
     *
     * Deliberately NOT offset — it measures the raw hardware, so applying a
     * correction to it would hide the very thing being measured.
     */
    public function calibration(
        float $widthMm,
        float $heightMm,
        string $printerName,
        bool $rotate = false
    ): string {
        return View::make('labels.calibration', [
            'widthMm'     => $widthMm,
            'heightMm'    => $heightMm,
            'printerName' => $printerName,
            // Calibration must go through the printer the same way a real
            // label does, rotation included — otherwise you measure one
            // orientation and print in another.
            'rotate'      => $rotate,
        ])->render();
    }

    /**
     * Flatten labels into one page per physical label.
     *
     * Copies become repeated pages rather than a copy count, because kiosk
     * printing ignores a count — the browser prints the document once and
     * whatever is in it is what comes off the roll.
     *
     * @return array<int, array{fields: array, last: bool}>
     */
    private function pages(array $labels, float $offsetXMm = 0, float $offsetYMm = 0): array
    {
        $pages = [];

        foreach ($labels as $label) {
            $template = $label['template'];
            $values   = $label['values'] ?? [];
            $copies   = max(1, (int) ($label['copies'] ?? 1));

            $fields = $this->fields($template, $values, $offsetXMm, $offsetYMm);

            for ($i = 0; $i < $copies; $i++) {
                $pages[] = ['fields' => $fields, 'last' => false];
            }
        }

        if ($pages) {
            // Marked in PHP rather than with :last-child — dompdf's selector
            // support for structural pseudo-classes is unreliable, and a
            // stray trailing page break wastes a physical label.
            $pages[array_key_last($pages)]['last'] = true;
        }

        return $pages;
    }

    /**
     * Resolve one template's positioned fields against a set of values.
     *
     * Unknown tokens and fields that resolve to nothing are dropped rather
     * than rendered blank, so a template built before a token existed still
     * prints cleanly.
     */
    private function fields(
        LabelTemplate $template,
        array $values,
        float $offsetXMm = 0,
        float $offsetYMm = 0
    ): array {
        $resolved = [];

        foreach ($template->fields() as $field) {
            $token = $field['token'] ?? null;

            if (! $token) {
                continue;
            }

            $text = $token === 'static'
                ? (string) ($field['text'] ?? '')
                : (string) ($values[$token] ?? '');

            if ($text === '') {
                continue;
            }

            $resolved[] = [
                'text'    => $text,
                'is_image' => $token === 'company.logo',
                'style'   => $this->style(
                    $field,
                    $offsetXMm,
                    $offsetYMm,
                    $token === 'company.logo' ? null : $text,
                ),
            ];
        }

        return $resolved;
    }

    /** Inline CSS for one field. Kept to properties dompdf implements. */
    private function style(
        array $field,
        float $offsetXMm = 0,
        float $offsetYMm = 0,
        ?string $text = null
    ): string {
        $bold     = ($field['weight'] ?? '') === 'bold';
        $fontSize = $this->fitFontSize($field, $text, $bold);

        $rules = [
            'left: ' . $this->mm(($field['x'] ?? 0) + $offsetXMm),
            'top: '  . $this->mm(($field['y'] ?? 0) + $offsetYMm),
        ];

        if (isset($field['w'])) {
            $rules[] = 'width: ' . $this->mm($field['w']);
        }

        if (isset($field['h'])) {
            $rules[] = 'height: ' . $this->mm($field['h']);
            // Belt and braces for the browser path. dompdf largely ignores
            // this, which is why the size is computed rather than clipped.
            $rules[] = 'overflow: hidden';
        }

        $rules[] = 'font-size: ' . $fontSize . 'pt';
        $rules[] = 'text-align: ' . $this->align($field['align'] ?? 'left');

        if ($bold) {
            $rules[] = 'font-weight: bold';
        }

        return implode('; ', $rules) . ';';
    }

    /**
     * Shrink a field's text until it fits its box.
     *
     * A long value used to wrap past the bottom of its own field and land on
     * top of the one below — a 27-character staff name needed 5.7mm in a 4mm
     * box and collided with the footer. Neither renderer clips: the browser
     * would need overflow:hidden honoured on an absolutely positioned box,
     * and dompdf ignores it outright.
     *
     * Truncating was the alternative and is worse on a food-safety label:
     * half an item name is a label nobody can act on. Smaller but complete
     * beats larger but cut off.
     *
     * Widths come from Helvetica's real per-character metrics rather than one
     * average for every character. The average was costing real size on the
     * field that matters most: a use-by is nearly all digits, which are narrow
     * (0.556 em) against the 0.62 the average assumed, so a date that fitted
     * at 18pt was being shrunk to 14.5.
     */
    private function fitFontSize(array $field, ?string $text, bool $bold): float
    {
        $size = (float) ($field['font_size'] ?? 8);

        $width  = (float) ($field['w'] ?? 0);
        $height = (float) ($field['h'] ?? 0);

        if ($text === null || $text === '' || $width <= 0 || $height <= 0) {
            return $size;
        }

        $lineRatio = 1.15;   // matches .f line-height in the Blade
        $widthPt   = $width / self::MM_PER_PT;

        while ($size > self::MIN_FONT_PT) {
            $lines  = HelveticaMetrics::lineCount($text, $bold, $size, $widthPt);
            $needed = $lines * $size * $lineRatio * self::MM_PER_PT;

            if ($needed <= $height) {
                break;
            }

            $size -= 0.5;
        }

        return round(max($size, self::MIN_FONT_PT), 1);
    }

    private function mm($value): string
    {
        return round((float) $value, 2) . 'mm';
    }

    private function align(string $align): string
    {
        return in_array($align, ['left', 'center', 'right'], true) ? $align : 'left';
    }
}
