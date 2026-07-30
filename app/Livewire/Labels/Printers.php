<?php

namespace App\Livewire\Labels;

use App\Models\LabelPrinter;
use App\Models\LabelTemplate;
use App\Models\Outlet;
use App\Services\LabelRenderService;
use App\Services\Labels\DefaultTemplates;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Label printers, one record per physical printer in an outlet.
 *
 * The record exists mainly to carry the loaded label stock size, which is
 * what the rendered document's page size comes from. The driver column is
 * shown but effectively fixed to 'browser' — PrintNode has no
 * implementation behind it yet, so offering it would be a dead end.
 */
class Printers extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $outlet_id = null;

    public string $name = '';

    public string $width_mm = '50';

    public string $height_mm = '25';

    public ?int $default_template_id = null;

    public bool $is_active = true;

    public string $offset_x_mm = '0';

    public string $offset_y_mm = '0';

    protected function rules(): array
    {
        return [
            'outlet_id'           => 'required|integer|exists:outlets,id',
            'name'                => 'required|string|max:100',
            'width_mm'            => 'required|numeric|min:10|max:300',
            'height_mm'           => 'required|numeric|min:10|max:300',
            'default_template_id' => 'nullable|integer|exists:label_templates,id',
            // Bounded: an offset this large means the wrong stock size is
            // configured, and shifting content won't save it.
            'offset_x_mm'         => 'required|numeric|min:-20|max:20',
            'offset_y_mm'         => 'required|numeric|min:-20|max:20',
        ];
    }

    /**
     * Print the ruler. Sent to the browser exactly like a real label, so it
     * exercises the same print path the chef will use.
     */
    public function printCalibration(int $id, LabelRenderService $renderer): void
    {
        $printer = LabelPrinter::findOrFail($id);

        $this->dispatch('label-print', document: $renderer->calibration(
            (float) $printer->width_mm,
            (float) $printer->height_mm,
            $printer->name,
        ));
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->outlet_id = Auth::user()->activeOutletId()
            ?? Outlet::where('company_id', Auth::user()->company_id)->value('id');
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $printer = LabelPrinter::findOrFail($id);

        $this->editingId           = $printer->id;
        $this->outlet_id           = $printer->outlet_id;
        $this->name                = $printer->name;
        $this->width_mm            = (string) rtrim(rtrim((string) $printer->width_mm, '0'), '.');
        $this->height_mm           = (string) rtrim(rtrim((string) $printer->height_mm, '0'), '.');
        $this->default_template_id = $printer->default_template_id;
        $this->is_active           = $printer->is_active;
        $this->offset_x_mm         = (string) (float) $printer->offset_x_mm;
        $this->offset_y_mm         = (string) (float) $printer->offset_y_mm;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'outlet_id'           => $this->outlet_id,
            'name'                => $this->name,
            'width_mm'            => (float) $this->width_mm,
            'height_mm'           => (float) $this->height_mm,
            'default_template_id' => $this->default_template_id ?: null,
            'is_active'           => $this->is_active,
            'offset_x_mm'         => (float) $this->offset_x_mm,
            'offset_y_mm'         => (float) $this->offset_y_mm,
        ];

        if ($this->editingId) {
            LabelPrinter::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Printer updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['driver']     = 'browser';
            LabelPrinter::create($data);
            session()->flash('success', 'Printer added.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        LabelPrinter::findOrFail($id)->delete();
        session()->flash('success', 'Printer removed.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.labels.printers', [
            'printers'  => LabelPrinter::with(['outlet', 'defaultTemplate'])
                ->orderBy('outlet_id')->orderBy('name')->get(),
            'outlets'   => Outlet::where('company_id', Auth::user()->company_id)
                ->orderBy('name')->get(),
            'templates' => LabelTemplate::orderBy('label_type')->orderBy('name')->get(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Label printers']);
    }

    private function resetForm(): void
    {
        $this->editingId           = null;
        $this->outlet_id           = null;
        $this->name                = '';
        $this->width_mm            = (string) (int) DefaultTemplates::WIDTH_MM;
        $this->height_mm           = (string) (int) DefaultTemplates::HEIGHT_MM;
        $this->default_template_id = null;
        $this->is_active           = true;
        $this->offset_x_mm         = '0';
        $this->offset_y_mm         = '0';
        $this->resetValidation();
    }
}
