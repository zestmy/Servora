<?php

namespace App\Livewire\Settings;

use App\Jobs\GenerateSopExport;
use App\Models\Recipe;
use App\Models\SopExport;
use App\Services\Pdf\SopExportBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The full-catalogue SOP export, and the panel that watches it.
 *
 * Its own component rather than part of LmsUsers on purpose: this polls every
 * couple of seconds while a render is running, and LmsUsers::render() does a
 * paginated user query plus six stat counts plus the category lists. Polling
 * that would re-run all of it every two seconds for a progress bar. Here the
 * poll costs one indexed row.
 */
class SopExportPanel extends Component
{
    /** Matches the job's own progress writes; slower reads as laggy. */
    private const POLL_MS = 2000;

    public ?int $exportId = null;

    public function mount(): void
    {
        $this->exportId = SopExport::forCompany(Auth::user()->company_id)
            ->latest('id')
            ->value('id');
    }

    public function start(SopExportBuilder $builder): void
    {
        $this->authorize('hr.view');

        $companyId = Auth::user()->company_id;

        // One at a time per company. There is a single queue worker, so a
        // second export would not start any sooner for waiting — it would just
        // sit behind the first holding a slot, and both would be ~500 MB when
        // they did run.
        if (SopExport::forCompany($companyId)->running()->exists()) {
            return;
        }

        $export = SopExport::create([
            'company_id'     => $companyId,
            'user_id'        => Auth::id(),
            'status'         => SopExport::STATUS_QUEUED,
            'label'          => $builder->describe(Auth::user(), []),
            'filters'        => [],
            'progress'       => 0,
            'progress_label' => 'Waiting for a worker',
        ]);

        $this->exportId = $export->id;

        GenerateSopExport::dispatch($export->id);
    }

    /** wire:poll target — the re-render is the point, so this is empty. */
    public function refresh(): void
    {
    }

    public function render()
    {
        $export = $this->exportId
            ? SopExport::forCompany(Auth::user()->company_id)->find($this->exportId)
            : null;

        return view('livewire.settings.sop-export-panel', [
            'export'   => $export,
            'running'  => (bool) $export?->isRunning(),
            'pollMs'   => self::POLL_MS,
            'sopCount' => Recipe::where('company_id', Auth::user()->company_id)
                ->where('is_active', true)
                ->where('exclude_from_lms', false)
                ->count(),
        ]);
    }
}
