<?php

namespace App\Jobs;

use App\Models\SopExport;
use App\Models\User;
use App\Services\Pdf\SopExportBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a SOP export off the request cycle.
 *
 * The whole-catalogue export is 203 recipes, ~500 MB peak and ~100 s — past
 * php-fpm's max_execution_time and far past what is safe to hold in one of
 * five workers on a 2 GB box. Here it is one process at a time, and the
 * Training Portal watches the SopExport row instead of a response.
 */
class GenerateSopExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One attempt.
     *
     * The default of three is wrong for this job: the realistic failure is
     * exhausting memory, and retrying that twice more just spends three
     * minutes each time doing the same thing to the same box — with a single
     * worker, that also blocks every other queued job behind it.
     */
    public $tries = 1;

    /** Measured at ~100 s for the full catalogue; this is room, not a target. */
    public $timeout = 600;

    /**
     * Ceiling for the render.
     *
     * The worker runs under CLI php, where memory_limit is -1 — unlimited,
     * which on a 2 GB box means a runaway render takes the whole server down
     * instead of failing one export. 768M is above the ~500 MB the full
     * catalogue measures and below anything that would put the box in swap.
     */
    private const RENDER_MEMORY = '768M';

    public function __construct(public int $exportId)
    {
    }

    public function handle(SopExportBuilder $builder): void
    {
        ini_set('memory_limit', self::RENDER_MEMORY);

        $export = SopExport::find($this->exportId);

        if (! $export || $export->status !== SopExport::STATUS_QUEUED) {
            return;
        }

        $user = User::find($export->user_id);

        if (! $user) {
            $this->markFailed($export, 'The user who requested this export no longer exists.');
            return;
        }

        $export->forceFill([
            'status'         => SopExport::STATUS_PROCESSING,
            'started_at'     => now(),
            'progress'       => 1,
            'progress_label' => 'Starting',
            'phase'          => SopExportBuilder::PHASE_COLLECTING,
        ])->save();

        // Throttled so a 203-recipe run writes about a hundred times, not
        // once per recipe per phase — the panel polls every two seconds and
        // cannot show more resolution than that anyway.
        $lastWritten = 0;
        $onProgress = function (int $percent, string $label, string $phase) use ($export, &$lastWritten) {
            if ($percent <= $lastWritten && $phase !== SopExportBuilder::PHASE_RENDERING) {
                return;
            }
            $lastWritten = $percent;
            $export->forceFill([
                'progress'       => min(99, $percent),
                'progress_label' => $label,
                'phase'          => $phase,
            ])->save();
        };

        try {
            $built = $builder->bulk($user, $export->filters ?? [], [], $onProgress);

            $path = 'sop-exports/' . $export->id . '-' . bin2hex(random_bytes(8)) . '.pdf';

            // output() before put() so a render that dies does not leave a
            // truncated file behind a "completed" row.
            Storage::disk('local')->put($path, $built['pdf']->output());

            $export->forceFill([
                'status'         => SopExport::STATUS_COMPLETED,
                'progress'       => 100,
                'progress_label' => 'Ready',
                'phase'          => null,
                'file_path'      => $path,
                'filename'       => $built['filename'],
                'file_size'      => Storage::disk('local')->size($path),
                'recipe_count'   => $built['recipeCount'],
                'finished_at'    => now(),
            ])->save();
        } catch (\Throwable $e) {
            $this->markFailed($export, $e->getMessage());

            throw $e;
        }
    }

    /**
     * A fatal (memory, timeout) kills the worker without unwinding, so handle()'s
     * catch never runs and the row would sit "processing" until it went stale.
     * Laravel calls this from the next worker instead.
     */
    public function failed(?\Throwable $e): void
    {
        $export = SopExport::find($this->exportId);

        if ($export && ! $export->isCompleted()) {
            $this->markFailed($export, $e?->getMessage() ?? 'The export stopped unexpectedly.');
        }
    }

    private function markFailed(SopExport $export, string $message): void
    {
        Log::error('SOP export failed', ['export_id' => $export->id, 'error' => $message]);

        $export->forceFill([
            'status'         => SopExport::STATUS_FAILED,
            'phase'          => null,
            'progress_label' => null,
            // Raw exception text can be a stack-trace-length string; the panel
            // shows this to a user, so keep it to something readable.
            'error'          => mb_strimwidth($message, 0, 300, '…'),
            'finished_at'    => now(),
        ])->save();
    }
}
