<?php

namespace App\Livewire\Audits;

use App\Models\AuditSchedule;
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

    /** Set when arriving from the Schedule screen; the schedule rolls forward on start. */
    public ?int $scheduleId = null;

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

        if ($id = (int) request('schedule')) {
            $schedule = AuditSchedule::with('template')->find($id);

            if ($schedule && Auth::user()->canAccessOutlet($schedule->outlet_id) && $schedule->template?->is_active) {
                $this->scheduleId = $schedule->id;
                $this->templateId = (string) $schedule->audit_template_id;
                $this->outlet_id  = $schedule->outlet_id;
            }
        }
    }

    public function start(AuditService $audits)
    {
        abort_unless(Auth::user()?->canDo('audits.conduct'), 403);

        $this->validate();

        $template = AuditTemplate::active()->findOrFail((int) $this->templateId);
        $outlet   = Outlet::findOrFail($this->resolveOutletId());

        $attrs = [
            'reference_number' => $this->reference ?: null,
            'time_in'          => now()->format('H:i'),
        ];

        // Only honour the schedule if the form and outlet still match it —
        // somebody who changed either on this screen is starting an ad-hoc
        // audit, and the plan should not roll forward for that.
        $schedule = $this->scheduleId ? AuditSchedule::find($this->scheduleId) : null;

        $audit = $schedule
            && $schedule->audit_template_id === $template->id
            && $schedule->outlet_id === $outlet->id
            ? $audits->startFromSchedule($schedule, Auth::user(), $this->auditDate, $attrs)
            : $audits->start($template, $outlet, Auth::user(), $this->auditDate, $attrs);

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
