<?php

namespace App\Livewire\Settings;

use App\Jobs\GenerateSopExport;
use App\Models\Outlet;
use App\Models\Recipe;
use App\Models\SopExport;
use App\Services\Pdf\SopExportBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use App\Traits\RequiresActiveCompany;

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
    use RequiresActiveCompany;

    /** Matches the job's own progress writes; slower reads as laggy. */
    private const POLL_MS = 2000;

    /** Outlet filter — '' is every SOP; an id keeps recipes tagged to it or to no outlet. */
    public string $outletId = '';

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

        $filters = $this->filters();

        $export = SopExport::create([
            'company_id'     => $companyId,
            'user_id'        => Auth::id(),
            'status'         => SopExport::STATUS_QUEUED,
            'label'          => $builder->describe(Auth::user(), $filters),
            'filters'        => $filters,
            'progress'       => 0,
            'progress_label' => 'Waiting for a worker',
        ]);

        GenerateSopExport::dispatch($export->id);
    }

    /** The selected outlet, only if it is one of this company's. */
    private function selectedOutletId(): ?int
    {
        $id = (int) $this->outletId;

        return $id && Outlet::where('company_id', $this->requireActiveCompany())->whereKey($id)->exists()
            ? $id
            : null;
    }

    private function filters(): array
    {
        $id = $this->selectedOutletId();

        return $id ? ['outlet' => $id] : [];
    }

    /**
     * This company's recent exports for the selected outlet (or for all
     * SOPs), newest first. filters is a JSON column, so the match is done
     * here rather than in SQL; the pruner keeps the row count small.
     */
    private function forSelection()
    {
        $outletId = (int) ($this->filters()['outlet'] ?? 0);

        return SopExport::forCompany($this->requireActiveCompany())
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn (SopExport $e) => (int) ($e->filters['outlet'] ?? 0) === $outletId)
            ->values();
    }

    /** wire:poll target — the re-render is the point, so this is empty. */
    public function refresh(): void
    {
    }

    /**
     * How long this company's last successful export took.
     *
     * The render is ~95% of the wait and cannot report progress from inside
     * dompdf, so the honest alternative to a fake percentage is a reference
     * point: "last one took 1m 46s" turns a blank 100-second stare into a
     * wait with a shape. Measured, not estimated — if nothing has finished
     * before, the panel simply omits it rather than guessing.
     */
    private function typicalSeconds(?SopExport $current): ?int
    {
        $previous = SopExport::forCompany($this->requireActiveCompany())
            ->where('status', SopExport::STATUS_COMPLETED)
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->latest('id')
            ->first(['started_at', 'finished_at']);

        if (! $previous) {
            return null;
        }

        $seconds = $previous->started_at->diffInSeconds($previous->finished_at);

        return $seconds > 0 ? (int) $seconds : null;
    }

    /**
     * The newest handbook for this selection that can actually be handed over.
     *
     * Separate from the export being watched: while a rebuild runs, or after
     * one fails, the previous good copy is still the current version, so the
     * panel keeps offering it until a newer one replaces it. The pruner
     * spares this row's file for the same reason.
     */
    private function latestDownloadable($rows): ?SopExport
    {
        return $rows->first(fn (SopExport $e) => $e->isDownloadable());
    }

    public function render()
    {
        $companyId = $this->requireActiveCompany();
        $rows      = $this->forSelection();

        // A run in progress is shown whatever outlet is picked — only one runs
        // at a time per company, so it is what the button is waiting on.
        $export = SopExport::forCompany($companyId)->running()->latest('id')->first()
            ?? $rows->first();

        $outletId = $this->selectedOutletId();

        return view('livewire.settings.sop-export-panel', [
            'export'   => $export,
            'latest'   => $this->latestDownloadable($rows),
            'running'  => (bool) $export?->isRunning(),
            'pollMs'   => self::POLL_MS,
            'typicalSeconds' => $this->typicalSeconds($export),
            'outlets'  => Outlet::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'sopCount' => Recipe::where('company_id', $companyId)
                ->where('is_active', true)
                ->where('exclude_from_lms', false)
                ->when($outletId, fn ($q) => $q->visibleToOutlets([$outletId]))
                ->count(),
        ]);
    }
}
