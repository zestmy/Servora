<?php

namespace App\Livewire\Audits;

use App\Models\AuditTemplate;
use App\Models\Outlet;
use App\Services\Audits\AuditService;
use App\Traits\PicksRecordOutlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Start an audit: which form, which outlet, what day. Three fields, then
 * straight into the conduct screen — an auditor standing in the outlet does
 * not want a form about the form.
 */
class Start extends Component
{
    use PicksRecordOutlet;

    public string $templateId = '';
    public string $auditDate  = '';
    public string $reference  = '';

    protected function rules(): array
    {
        return [
            'templateId' => 'required|integer',
            'outlet_id'  => 'required|integer',
            'auditDate'  => 'required|date',
            'reference'  => 'nullable|string|max:40',
        ];
    }

    protected function messages(): array
    {
        return [
            'templateId.required' => 'Choose which audit form to use.',
            'outlet_id.required'  => 'Choose the outlet being audited.',
        ];
    }

    public function mount(): void
    {
        $this->auditDate = now()->toDateString();
        $this->initOutlet();

        $templates = AuditTemplate::active()->ordered()->get(['id']);

        if ($templates->count() === 1) {
            $this->templateId = (string) $templates->first()->id;
        }
    }

    public function start(AuditService $audits)
    {
        abort_unless(Auth::user()?->canDo('audits.conduct'), 403);

        $this->validate();

        $template = AuditTemplate::active()->findOrFail((int) $this->templateId);
        $outlet   = Outlet::findOrFail($this->resolveOutletId());

        $audit = $audits->start($template, $outlet, Auth::user(), $this->auditDate, [
            'reference_number' => $this->reference ?: null,
            'time_in'          => now()->format('H:i'),
        ]);

        return $this->redirect(route('audits.show', $audit->id), navigate: true);
    }

    public function render()
    {
        return view('livewire.audits.start', [
            'templates'       => AuditTemplate::active()->ordered()->withCount('sections')->get(),
            'outlets'         => $this->outletOptions(),
            'hasOutletChoice' => $this->hasOutletChoice(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'New Audit']);
    }
}
