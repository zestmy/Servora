<?php

namespace App\Livewire\Labels;

use App\Models\LabelTemplate;
use App\Services\LabelRenderService;
use App\Services\Labels\CompanyLogo;
use Livewire\Component;

/**
 * Lays out one label.
 *
 * Positioning is numeric — click a field, then set its millimetre
 * coordinates — rather than drag-and-drop. Two reasons: a label is 70mm
 * across and the difference between 3mm and 4mm matters, which is finicky
 * to hit by dragging; and numbers are exact, so a chef can reproduce a
 * layout by reading it off. Nudge buttons cover the "just move it a bit"
 * case.
 *
 * The preview is the real renderer, not an approximation of it, so what is
 * on screen is what comes off the roll.
 */
class TemplateDesigner extends Component
{
    public LabelTemplate $template;

    public string $name = '';

    public string $width_mm = '70';

    public string $height_mm = '40';

    /** Working copy of layout.fields — not persisted until save(). */
    public array $fields = [];

    public ?int $selected = null;

    public string $newToken = 'item.name';

    public function mount(LabelTemplate $template): void
    {
        $this->template  = $template;
        $this->name      = $template->name;
        $this->width_mm  = (string) (float) $template->width_mm;
        $this->height_mm = (string) (float) $template->height_mm;
        $this->fields    = array_map(fn ($f) => $this->normalise($f), $template->fields());
    }

    public function addField(): void
    {
        $this->fields[] = $this->normalise([
            'token' => $this->newToken,
            'x' => 3, 'y' => 3, 'w' => 30, 'h' => 5,
            'font_size' => 8, 'align' => 'left', 'weight' => 'normal',
            'text' => $this->newToken === 'static' ? 'Text' : '',
        ]);

        $this->selected = array_key_last($this->fields);
    }

    public function selectField(int $index): void
    {
        $this->selected = isset($this->fields[$index]) ? $index : null;
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields   = array_values($this->fields);
        $this->selected = null;
    }

    /** Nudge in millimetres, clamped so a field can't be pushed off the label. */
    public function nudge(int $index, float $dx, float $dy): void
    {
        if (! isset($this->fields[$index])) {
            return;
        }

        $f = $this->fields[$index];

        $this->fields[$index]['x'] = $this->clamp(
            (float) $f['x'] + $dx, 0, (float) $this->width_mm - (float) $f['w']
        );
        $this->fields[$index]['y'] = $this->clamp(
            (float) $f['y'] + $dy, 0, (float) $this->height_mm - (float) $f['h']
        );
    }

    public function save(): void
    {
        $this->validate([
            'name'      => 'required|string|max:100',
            'width_mm'  => 'required|numeric|min:10|max:300',
            'height_mm' => 'required|numeric|min:10|max:300',
        ]);

        $this->template->update([
            'name'      => $this->name,
            'width_mm'  => (float) $this->width_mm,
            'height_mm' => (float) $this->height_mm,
            'layout'    => ['fields' => array_values(array_map(
                fn ($f) => $this->normalise($f),
                $this->fields
            ))],
        ]);

        session()->flash('success', 'Template saved.');
    }

    public function render(LabelRenderService $renderer)
    {
        $w = (float) $this->width_mm;
        $h = (float) $this->height_mm;

        // Preview through the real renderer against a working copy, so an
        // unsaved change is visible before committing it.
        $preview = clone $this->template;
        $preview->layout    = ['fields' => array_map(fn ($f) => $this->normalise($f), $this->fields)];
        $preview->width_mm  = $w;
        $preview->height_mm = $h;

        return view('livewire.labels.template-designer', [
            'tokens'    => LabelTemplate::TOKENS,
            'overflow'  => $this->overflowing($w, $h),
            'previewHtml' => $renderer->html(
                [['template' => $preview, 'values' => $this->sampleValues(), 'copies' => 1]],
                $w,
                $h
            ),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Design: ' . $this->template->name]);
    }

    /**
     * Fields hanging off the edge of the label.
     *
     * Worth flagging loudly: anything past the page makes the browser
     * paginate at print time, and each extra page is a wasted physical
     * label. That bug cost real stock before it was caught.
     *
     * @return array<int, string>
     */
    private function overflowing(float $w, float $h): array
    {
        $bad = [];

        foreach ($this->fields as $i => $f) {
            $right  = (float) $f['x'] + (float) $f['w'];
            $bottom = (float) $f['y'] + (float) $f['h'];

            if ($right > $w || $bottom > $h) {
                $bad[$i] = sprintf('extends to %.1f × %.1f mm', $right, $bottom);
            }
        }

        return $bad;
    }

    /** Realistic stand-ins so the preview shows true text lengths. */
    private function sampleValues(): array
    {
        return [
            'item.name'           => 'CHICKEN THIGH BONELESS',
            'item.code'           => 'ING-0412',
            'label.caption'       => $this->template->caption() ?: 'USE BY',
            'date.start'          => 'FRI 31/07/2026 14:30',
            'date.start_date'     => 'FRI 31/07/2026',
            'date.start_time'     => '14:30',
            'date.start_day'      => 'FRI',
            'date.end'            => 'MON 03/08/2026 23:59',
            'date.end_date'       => 'MON 03/08/2026',
            'date.end_time'       => '23:59',
            'date.end_day'        => 'MON',
            'staff.name'          => 'Aiman',
            'outlet.name'         => 'Main Kitchen',
            'storage.instruction' => 'Chilled',
            'quantity'            => '2.5 kg',
            'batch.ref'           => 'B-1042',
            // The real logo, not a placeholder — this is the one field whose
            // proportions can't be guessed from a stand-in, and the preview
            // is only worth having if it shows what prints.
            'company.logo'        => (string) app(CompanyLogo::class)
                ->dataUri($this->template->company_id),
            'footer'              => 'Keep refrigerated below 4°C',
        ];
    }

    /** Fill in defaults so a partial field never breaks the renderer. */
    private function normalise(array $f): array
    {
        return [
            'token'     => $f['token'] ?? 'static',
            'x'         => round((float) ($f['x'] ?? 0), 2),
            'y'         => round((float) ($f['y'] ?? 0), 2),
            'w'         => round((float) ($f['w'] ?? 20), 2),
            'h'         => round((float) ($f['h'] ?? 5), 2),
            'font_size' => (float) ($f['font_size'] ?? 8),
            'align'     => in_array($f['align'] ?? 'left', ['left', 'center', 'right'], true) ? $f['align'] : 'left',
            'weight'    => ($f['weight'] ?? '') === 'bold' ? 'bold' : 'normal',
            'text'      => (string) ($f['text'] ?? ''),
        ];
    }

    private function clamp(float $v, float $min, float $max): float
    {
        return round(max($min, min($max, $v)), 2);
    }
}
