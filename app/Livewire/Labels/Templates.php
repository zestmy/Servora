<?php

namespace App\Livewire\Labels;

use App\Models\LabelTemplate;
use App\Services\LabelTemplateService;
use App\Services\Labels\DefaultTemplates;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Template list. Creating, duplicating and choosing the default for each
 * label type; the layout itself is edited in the designer.
 *
 * Duplicating is the intended way to customise: copy a stock template, edit
 * the copy, make it the default. The original stays as a fallback, which
 * matters because a company that mangles its only "use by" template has no
 * way to print the most-used label in the system.
 */
class Templates extends Component
{
    public bool $showModal = false;

    public string $name = '';

    public string $label_type = 'prep';

    public string $width_mm = '70';

    public string $height_mm = '40';

    public function mount(): void
    {
        app(LabelTemplateService::class)->ensureDefaults(Auth::user()->company_id);
    }

    public function openCreate(): void
    {
        $this->name       = '';
        $this->label_type = 'prep';
        $this->width_mm   = (string) (int) DefaultTemplates::WIDTH_MM;
        $this->height_mm  = (string) (int) DefaultTemplates::HEIGHT_MM;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function create()
    {
        $this->validate([
            'name'       => 'required|string|max:100',
            'label_type' => 'required|in:' . implode(',', array_keys(LabelTemplate::LABEL_TYPES)),
            'width_mm'   => 'required|numeric|min:10|max:300',
            'height_mm'  => 'required|numeric|min:10|max:300',
        ]);

        $template = LabelTemplate::create([
            'company_id' => Auth::user()->company_id,
            'name'       => $this->name,
            'label_type' => $this->label_type,
            'width_mm'   => (float) $this->width_mm,
            'height_mm'  => (float) $this->height_mm,
            'engine'     => 'pdf',
            // Start from the stock arrangement rather than a blank label —
            // positioning eight fields from nothing is nobody's idea of fun.
            'layout'     => ['fields' => DefaultTemplates::FIELDS],
            'is_default' => false,
        ]);

        $this->showModal = false;

        return $this->redirect(route('labels.templates.design', $template), navigate: true);
    }

    public function duplicate(int $id)
    {
        $source = LabelTemplate::findOrFail($id);

        $copy = $source->replicate(['is_default']);
        $copy->name       = $this->uniqueCopyName($source->name);
        $copy->is_default = false;
        $copy->save();

        return $this->redirect(route('labels.templates.design', $copy), navigate: true);
    }

    /**
     * Exactly one default per label type — clearing the others here rather
     * than trusting a unique index, because "two defaults" resolves
     * arbitrarily at print time and the chef would never know which won.
     */
    public function makeDefault(int $id): void
    {
        $template = LabelTemplate::findOrFail($id);

        LabelTemplate::where('label_type', $template->label_type)
            ->where('id', '!=', $template->id)
            ->update(['is_default' => false]);

        $template->update(['is_default' => true]);

        session()->flash('success', $template->name . ' is now the default for ' . $template->label_type . ' labels.');
    }

    public function delete(int $id): void
    {
        $template = LabelTemplate::findOrFail($id);

        // Refuse to remove the last template for a type. Printing that label
        // would simply stop working, and the chef finds out at the printer.
        $remaining = LabelTemplate::where('label_type', $template->label_type)
            ->where('id', '!=', $template->id)
            ->count();

        if ($remaining === 0) {
            session()->flash('error', 'This is the only template for ' . $template->label_type
                . ' labels. Create a replacement before deleting it.');

            return;
        }

        $wasDefault = $template->is_default;
        $type       = $template->label_type;
        $template->delete();

        // Never leave a type without a default.
        if ($wasDefault) {
            LabelTemplate::where('label_type', $type)->limit(1)->update(['is_default' => true]);
        }

        session()->flash('success', 'Template deleted.');
    }

    public function render()
    {
        return view('livewire.labels.templates', [
            'templates'  => LabelTemplate::orderBy('label_type')->orderByDesc('is_default')->orderBy('name')->get()
                ->groupBy('label_type'),
            'labelTypes' => LabelTemplate::LABEL_TYPES,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Label templates']);
    }

    private function uniqueCopyName(string $name): string
    {
        $base = preg_replace('/ \(copy( \d+)?\)$/', '', $name);

        for ($i = 1; $i < 50; $i++) {
            $candidate = $i === 1 ? "{$base} (copy)" : "{$base} (copy {$i})";

            if (! LabelTemplate::where('name', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $base . ' (copy)';
    }
}
