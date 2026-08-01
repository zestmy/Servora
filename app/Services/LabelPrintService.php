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
use Illuminate\Support\Arr;
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
        private \App\Services\Labels\CompanyLogo $logo,
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

        // Enforced here, not only in the forms. An audit row that names
        // nobody is the one thing this log exists to prevent: "who prepped
        // this" is the question an auditor asks, and a blank answer makes
        // every other field on the record academic.
        if (! $employeeId) {
            throw new \InvalidArgumentException('Select who is preparing these before printing.');
        }

        // Resolved once, used by every line and every date on every label.
        $printedAt = Carbon::now();

        // From the printer, not the logged-in user. Staff on the subdomain
        // app authenticate with a PIN and have no web user at all, so
        // Auth::user() is null for them — the printer always knows which
        // company and outlet it belongs to.
        $companyId = $printer->company_id;
        $settings  = LabelSetting::forCompany($companyId);
        $employee  = $employeeId ? Employee::find($employeeId) : null;

        // Resolved once for the whole run, not per line: it is the same
        // image on every label and encoding it is the expensive part.
        $logo = $this->logo->dataUri($companyId);

        return DB::transaction(function () use (
            $lines, $printer, $employee, $labelSetId, $printedAt, $companyId, $settings, $logo
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
                $prepared = $this->prepareLine($line, $printer, $employee, $printedAt, $settings, $batch, $logo);

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
        LabelPrintBatch $batch,
        ?string $logo = null
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
            $logo,
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
                'values'     => Arr::except($values, ['company.logo']),
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
        string $batchRef,
        ?string $logo = null
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
            // The weekday leads every date. In a kitchen the question is
            // "is this still good today?", and a three-letter day answers it
            // without anyone working out what today's date is. Uppercase to
            // match the rest of the label, and abbreviated because the
            // use-by is set at 18pt and has one line to live on.
            'date.start'          => strtoupper($start->format('D d/m/Y H:i')),
            'date.start_date'     => strtoupper($start->format('D d/m/Y')),
            'date.start_time'     => $start->format('H:i'),
            'date.start_day'      => strtoupper($start->format('D')),
            'date.end'            => $end ? strtoupper($end->format('D d/m/Y H:i')) : '',
            'date.end_date'       => $end ? strtoupper($end->format('D d/m/Y')) : '',
            'date.end_time'       => $end?->format('H:i') ?? '',
            'date.end_day'        => $end ? strtoupper($end->format('D')) : '',
            'staff.name'          => (string) ($employee->name ?? ''),
            'outlet.name'         => (string) ($printer->outlet->name ?? ''),
            'storage.instruction' => ShelfLifeRule::stateLabel($storageState),
            'quantity'            => $quantity,
            'batch.ref'           => $batchRef,
            'footer'              => (string) ($settings->footer_text ?? ''),
            // A base64 image, not text. Kept out of the frozen payload below
            // — storing it per print row would put megabytes of duplicated
            // logo into label_prints for no gain, and the logo is not part of
            // what the label SAID.
            'company.logo'        => (string) $logo,
        ], fn ($v) => $v !== '');
    }

    /**
     * Preview a use-by without printing — what the print screen shows next
     * to each queued line so a chef sees the date before committing a label.
     *
     * @return array{end_at: CarbonInterface|null, manual: bool, source: ?string}
     */
    public function previewUseBy(
        ?Model $item,
        string $storageState,
        ?CarbonInterface $at = null,
        ?int $companyId = null
    ): array {
        // Falls back to the logged-in user, but PIN staff have none, so the
        // caller passes the company explicitly there.
        $companyId ??= Auth::user()?->company_id;

        $computed = $this->shelfLife->computeUseBy(
            $item,
            $storageState,
            $at ?? Carbon::now(),
            $companyId
        );

        return [
            'end_at' => $computed['end_at'],
            'manual' => $computed['manual'],
            'source' => $computed['shelf_life']['source'] ?? null,
        ];
    }
}
