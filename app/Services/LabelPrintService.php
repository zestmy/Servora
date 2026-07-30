<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Ingredient;
use App\Models\LabelPrint;
use App\Models\LabelPrintBatch;
use App\Models\LabelPrinter;
use App\Models\LabelSetting;
use App\Models\LabelTemplate;
use App\Models\ProductionRecipe;
use App\Models\Recipe;
use App\Models\ShelfLifeRule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Turns a list of things a chef wants labelled into printed labels and a
 * compliance record.
 *
 * Two rules this service exists to enforce:
 *
 *  1. ONE timestamp for the whole batch. Resolving now() per line makes the
 *     times drift across a single chiller relabel, which looks like sloppy
 *     record keeping to an auditor and makes two labels applied together
 *     disagree with each other.
 *  2. The payload written to label_prints is a FROZEN snapshot of exactly
 *     what went on the label. Nothing downstream may re-derive a past label
 *     from the live item — its name and shelf life will have moved on.
 */
class LabelPrintService
{
    /** Morph keys the print screen can label. */
    public const LABELABLE = [
        'ingredient' => Ingredient::class,
        'recipe'     => Recipe::class,
        'production' => ProductionRecipe::class,
    ];

    public function __construct(
        private ShelfLifeService $shelfLife,
        private LabelTemplateService $templates,
        private \App\Services\Labels\DriverFactory $drivers,
    ) {
    }

    /**
     * Print a batch.
     *
     * Each line: label_type, storage_state, copies, and either
     * labelable_type + labelable_id or custom_name. Optional: quantity,
     * uom_label, template_id, end_at (a manual override typed by the chef
     * when no rule resolved).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{batch: LabelPrintBatch, document: ?string, labels: int}
     */
    public function print(
        array $lines,
        LabelPrinter $printer,
        ?int $employeeId = null,
        ?int $labelSetId = null
    ): array {
        if (! $lines) {
            throw new \InvalidArgumentException('Nothing to print.');
        }

        // Resolved once, used by every line and every date on every label.
        $printedAt = Carbon::now();

        $companyId = Auth::user()->company_id;
        $settings  = LabelSetting::forCompany($companyId);
        $employee  = $employeeId ? Employee::find($employeeId) : null;

        return DB::transaction(function () use (
            $lines, $printer, $employee, $labelSetId, $printedAt, $companyId, $settings
        ) {
            $batch = LabelPrintBatch::create([
                'company_id'   => $companyId,
                'outlet_id'    => $printer->outlet_id,
                'label_set_id' => $labelSetId,
                'employee_id'  => $employee?->id,
                'user_id'      => Auth::id(),
                'printed_at'   => $printedAt,
                'item_count'   => 0,
                'label_count'  => 0,
            ]);

            $renderable  = [];
            $labelCount  = 0;

            foreach ($lines as $line) {
                $prepared = $this->prepareLine($line, $printer, $employee, $printedAt, $settings, $batch);

                if (! $prepared) {
                    continue;
                }

                $renderable[] = $prepared['renderable'];
                $labelCount  += $prepared['copies'];
            }

            if (! $renderable) {
                throw new \RuntimeException('No printable lines — every item is missing a template.');
            }

            $driver = $this->drivers->for($printer);
            $result = $driver->submit($renderable, $printer);

            $batch->update([
                'item_count'    => count($renderable),
                'label_count'   => $labelCount,
                'driver'        => $driver->name(),
                'driver_job_id' => $result['job_id'] ?? null,
            ]);

            // The rows were written before the driver ran, because building
            // them is what produces the document to submit. Apply the real
            // status now: 'sent' means we handed it to a browser and cannot
            // know more, 'queued' means PrintNode accepted it.
            LabelPrint::where('batch_id', $batch->id)
                ->update(['status' => $result['status'] ?? 'sent']);

            return [
                'batch'    => $batch,
                'document' => $result['document'] ?? null,
                'labels'   => $labelCount,
            ];
        });
    }

    /**
     * Build one line's label data, write its log row, and return what the
     * renderer needs. Returns null when no template exists for the type.
     */
    private function prepareLine(
        array $line,
        LabelPrinter $printer,
        ?Employee $employee,
        CarbonInterface $printedAt,
        LabelSetting $settings,
        LabelPrintBatch $batch
    ): ?array {
        $labelType    = $line['label_type'] ?? 'prep';
        $storageState = $line['storage_state'] ?? 'chill';
        $copies       = max(1, (int) ($line['copies'] ?? 1));

        $item = $this->resolveItem($line);

        $template = $this->templates->resolveFor(
            $batch->company_id,
            $labelType,
            $line['template_id'] ?? $printer->default_template_id
        );

        if (! $template) {
            return null;
        }

        // A chef-typed date always wins: it is only ever entered because
        // nothing resolved, and overriding it would silently discard input.
        $manualEnd = ! empty($line['end_at']) ? Carbon::parse($line['end_at']) : null;

        $computed = $manualEnd
            ? ['end_at' => $manualEnd, 'shelf_life' => null, 'manual' => true]
            : $this->shelfLife->computeUseBy($item, $storageState, $printedAt, $batch->company_id);

        $name = $item?->name ?? (string) ($line['custom_name'] ?? '');

        $values = $this->tokenValues(
            $name,
            $item,
            $template,
            $printedAt,
            $computed['end_at'],
            $employee,
            $printer,
            $storageState,
            $line,
            $settings,
            'B-' . $batch->id,
        );

        LabelPrint::create([
            'company_id'     => $batch->company_id,
            'outlet_id'      => $batch->outlet_id,
            'batch_id'       => $batch->id,
            'printer_id'     => $printer->id,
            'template_id'    => $template->id,
            'labelable_type' => $item ? $item::class : null,
            'labelable_id'   => $item?->getKey(),
            'custom_name'    => $item ? null : $name,
            'label_type'     => $labelType,
            'storage_state'  => $storageState,
            'start_at'       => $printedAt,
            'end_at'         => $computed['end_at'],
            'manual_expiry'  => $computed['manual'],
            'copies'         => $copies,
            // Frozen. Read this, never the live item.
            'payload'        => [
                'item_name'  => $name,
                'values'     => $values,
                'shelf_life' => $computed['shelf_life'],
                'template'   => ['id' => $template->id, 'name' => $template->name],
            ],
            'status'         => 'sent',
        ]);

        return [
            'copies'     => $copies,
            'renderable' => [
                'template' => $template,
                'values'   => $values,
                'copies'   => $copies,
            ],
        ];
    }

    /** Resolve a line's linked item, if it has one. */
    private function resolveItem(array $line): ?Model
    {
        $type = $line['labelable_type'] ?? null;
        $id   = $line['labelable_id'] ?? null;

        if (! $type || ! $id) {
            return null;
        }

        // Accept both a short key from the UI and a full class name from a
        // stored set line.
        $class = self::LABELABLE[$type] ?? (class_exists($type) ? $type : null);

        if (! $class) {
            return null;
        }

        return $class::find($id);
    }

    /**
     * Everything a template can position, resolved to strings.
     *
     * Dates use d/m/Y to match how the rest of Servora shows them. The
     * renderer drops any token that resolves to an empty string, so a
     * template carrying a field this line has no value for prints cleanly
     * rather than leaving a gap.
     */
    private function tokenValues(
        string $name,
        ?Model $item,
        LabelTemplate $template,
        CarbonInterface $start,
        ?CarbonInterface $end,
        ?Employee $employee,
        LabelPrinter $printer,
        string $storageState,
        array $line,
        LabelSetting $settings,
        string $batchRef
    ): array {
        $quantity = '';

        if (! empty($line['quantity'])) {
            $quantity = trim(rtrim(rtrim(number_format((float) $line['quantity'], 3, '.', ''), '0'), '.')
                . ' ' . ($line['uom_label'] ?? ''));
        }

        return array_filter([
            'item.name'           => $name,
            'item.code'           => (string) ($item->code ?? ''),
            'label.caption'       => $template->caption(),
            'date.start'          => $start->format('d/m/Y H:i'),
            'date.start_date'     => $start->format('d/m/Y'),
            'date.start_time'     => $start->format('H:i'),
            'date.end'            => $end?->format('d/m/Y H:i') ?? '',
            'date.end_date'       => $end?->format('d/m/Y') ?? '',
            'date.end_time'       => $end?->format('H:i') ?? '',
            'staff.name'          => (string) ($employee->name ?? ''),
            'outlet.name'         => (string) ($printer->outlet->name ?? ''),
            'storage.instruction' => ShelfLifeRule::stateLabel($storageState),
            'quantity'            => $quantity,
            'batch.ref'           => $batchRef,
            'footer'              => (string) ($settings->footer_text ?? ''),
        ], fn ($v) => $v !== '');
    }

    /**
     * Preview a use-by without printing — what the print screen shows next
     * to each queued line so a chef sees the date before committing a label.
     *
     * @return array{end_at: CarbonInterface|null, manual: bool, source: ?string}
     */
    public function previewUseBy(?Model $item, string $storageState, ?CarbonInterface $at = null): array
    {
        $computed = $this->shelfLife->computeUseBy(
            $item,
            $storageState,
            $at ?? Carbon::now(),
            Auth::user()->company_id
        );

        return [
            'end_at' => $computed['end_at'],
            'manual' => $computed['manual'],
            'source' => $computed['shelf_life']['source'] ?? null,
        ];
    }
}
