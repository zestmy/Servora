<?php

namespace App\Livewire\Labels;

use App\Models\LabelSetting;
use App\Models\LabelTemplate;
use App\Services\LabelTemplateService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Company-level label printing settings.
 *
 * Deliberately does NOT expose the PrintNode API key. Nothing reads it under
 * the browser driver, and a settings field for a credential that does
 * nothing invites someone to paste a real key into a box that never uses it.
 * It gets a UI when a PrintNode driver ships.
 */
class Settings extends Component
{
    public string $use_by_rounding = 'eod';

    public string $footer_text = '';

    public ?int $default_template_id = null;

    public function mount(): void
    {
        // Seeding here means a company that has never opened the label
        // screens still has templates to choose from in the dropdown below.
        app(LabelTemplateService::class)->ensureDefaults(Auth::user()->company_id);

        $settings = LabelSetting::forCompany(Auth::user()->company_id);

        $this->use_by_rounding     = $settings->use_by_rounding ?: 'eod';
        $this->footer_text         = (string) $settings->footer_text;
        $this->default_template_id = $settings->default_template_id;
    }

    protected function rules(): array
    {
        return [
            'use_by_rounding'     => 'required|in:' . implode(',', array_keys(LabelSetting::ROUNDING_OPTIONS)),
            'footer_text'         => 'nullable|string|max:120',
            'default_template_id' => 'nullable|integer|exists:label_templates,id',
        ];
    }

    public function save(): void
    {
        $this->validate();

        LabelSetting::forCompany(Auth::user()->company_id)->update([
            'use_by_rounding'     => $this->use_by_rounding,
            'footer_text'         => $this->footer_text ?: null,
            'default_template_id' => $this->default_template_id ?: null,
        ]);

        session()->flash('success', 'Label settings saved.');
    }

    public function render()
    {
        return view('livewire.labels.settings', [
            'roundingOptions' => LabelSetting::ROUNDING_OPTIONS,
            'templates'       => LabelTemplate::orderBy('label_type')->orderBy('name')->get(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Label settings']);
    }
}
