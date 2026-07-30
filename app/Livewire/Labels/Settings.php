<?php

namespace App\Livewire\Labels;

use App\Models\LabelSetting;
use App\Models\LabelTemplate;
use App\Services\LabelTemplateService;
use App\Services\Labels\PrintNodeClient;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Company-level label printing settings.
 *
 * The PrintNode API key is write-only here. The stored value is NEVER bound
 * to a form field or sent to the browser — the screen only reports whether
 * one is set. Typing a new key replaces it; leaving the box empty keeps
 * what's there. A credential that round-trips through the DOM on every page
 * load is a credential waiting to end up in a screenshot or a bug report.
 */
class Settings extends Component
{
    public string $use_by_rounding = 'eod';

    public string $footer_text = '';

    public ?int $default_template_id = null;

    /** Always blank on load. Only ever holds a NEW key being entered. */
    public string $printnode_api_key = '';

    public bool $hasApiKey = false;

    public ?string $connectionResult = null;

    public function mount(): void
    {
        // Seeding here means a company that has never opened the label
        // screens still has templates to choose from in the dropdown below.
        app(LabelTemplateService::class)->ensureDefaults(Auth::user()->company_id);

        $settings = LabelSetting::forCompany(Auth::user()->company_id);

        $this->use_by_rounding     = $settings->use_by_rounding ?: 'eod';
        $this->footer_text         = (string) $settings->footer_text;
        $this->default_template_id = $settings->default_template_id;

        // Presence only. The key itself never leaves the server.
        $this->hasApiKey = filled($settings->printnode_api_key);
    }

    protected function rules(): array
    {
        return [
            'use_by_rounding'     => 'required|in:' . implode(',', array_keys(LabelSetting::ROUNDING_OPTIONS)),
            'footer_text'         => 'nullable|string|max:120',
            'default_template_id' => 'nullable|integer|exists:label_templates,id',
            'printnode_api_key'   => 'nullable|string|max:200',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'use_by_rounding'     => $this->use_by_rounding,
            'footer_text'         => $this->footer_text ?: null,
            'default_template_id' => $this->default_template_id ?: null,
        ];

        // Only touch the key when a new one was typed. An empty box means
        // "leave it alone", not "delete it" — otherwise every unrelated save
        // would silently wipe the credential.
        if (filled($this->printnode_api_key)) {
            $data['printnode_api_key'] = trim($this->printnode_api_key);
        }

        LabelSetting::forCompany(Auth::user()->company_id)->update($data);

        $this->printnode_api_key = '';
        $this->hasApiKey         = filled(LabelSetting::forCompany(Auth::user()->company_id)->printnode_api_key);

        session()->flash('success', 'Label settings saved.');
    }

    /** Explicit removal, since an empty field deliberately means "keep". */
    public function clearApiKey(): void
    {
        LabelSetting::forCompany(Auth::user()->company_id)->update(['printnode_api_key' => null]);

        $this->printnode_api_key = '';
        $this->hasApiKey         = false;
        $this->connectionResult  = null;

        session()->flash('success', 'PrintNode API key removed.');
    }

    /**
     * Check the stored key against PrintNode before a chef discovers it's
     * wrong at the printer.
     */
    public function testConnection(): void
    {
        $key = filled($this->printnode_api_key)
            ? trim($this->printnode_api_key)
            : LabelSetting::forCompany(Auth::user()->company_id)->printnode_api_key;

        if (! $key) {
            $this->connectionResult = 'No API key to test.';

            return;
        }

        try {
            $printers = (new PrintNodeClient($key))->printers();

            $this->connectionResult = sprintf(
                'Connected. %d printer%s visible%s',
                count($printers),
                count($printers) === 1 ? '' : 's',
                $printers ? ': ' . implode(', ', array_column($printers, 'name')) : '.'
            );
        } catch (\Throwable $e) {
            $this->connectionResult = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.labels.settings', [
            'roundingOptions' => LabelSetting::ROUNDING_OPTIONS,
            'templates'       => LabelTemplate::orderBy('label_type')->orderBy('name')->get(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Label settings']);
    }
}
