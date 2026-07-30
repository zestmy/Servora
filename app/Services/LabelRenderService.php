<?php

namespace App\Services;

use App\Models\LabelTemplate;
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
        float $offsetYMm = 0
    ): string {
        return View::make('labels.document', [
            'pages'    => $this->pages($labels, $offsetXMm, $offsetYMm),
            'widthMm'  => $widthMm,
            'heightMm' => $heightMm,
        ])->render();
    }

    /**
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

        return Pdf::loadHTML($this->html($labels, $widthMm, $heightMm, $offsetXMm, $offsetYMm))
            ->setPaper($paper)
            ->output();
    }

    /**
     * The calibration label: a ruler for measuring what a printer clips.
     *
     * Deliberately NOT offset — it measures the raw hardware, so applying a
     * correction to it would hide the very thing being measured.
     */
    public function calibration(float $widthMm, float $heightMm, string $printerName): string
    {
        return View::make('labels.calibration', [
            'widthMm'     => $widthMm,
            'heightMm'    => $heightMm,
            'printerName' => $printerName,
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
                'style'   => $this->style($field, $offsetXMm, $offsetYMm),
            ];
        }

        return $resolved;
    }

    /** Inline CSS for one field. Kept to properties dompdf implements. */
    private function style(array $field, float $offsetXMm = 0, float $offsetYMm = 0): string
    {
        $rules = [
            'left: ' . $this->mm(($field['x'] ?? 0) + $offsetXMm),
            'top: '  . $this->mm(($field['y'] ?? 0) + $offsetYMm),
        ];

        if (isset($field['w'])) {
            $rules[] = 'width: ' . $this->mm($field['w']);
        }

        if (isset($field['h'])) {
            $rules[] = 'height: ' . $this->mm($field['h']);
        }

        $rules[] = 'font-size: ' . (float) ($field['font_size'] ?? 8) . 'pt';
        $rules[] = 'text-align: ' . $this->align($field['align'] ?? 'left');

        if (($field['weight'] ?? '') === 'bold') {
            $rules[] = 'font-weight: bold';
        }

        return implode('; ', $rules) . ';';
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
