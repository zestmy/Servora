<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LabourCostTransfer;
use App\Models\Outlet;
use App\Services\Hr\LabourCostTransferCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * One labour cost transfer as a PDF, laid out like the OT claim form.
 *
 * Behind hr.compensation on the route (it prints salary rates), and — like the
 * screen — only for someone who can see an outlet at either end of it.
 */
class LabourCostTransferPdfController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        $data = $this->load($request, $id);

        return Pdf::loadView('pdf.labour-cost-transfer', $data)
            ->setPaper('a4', 'portrait')
            ->download('Labour-Cost-Transfer-' . $data['transfer']->transfer_number . '.pdf');
    }

    /**
     * The transfer, its outlet summary and its company, access-checked.
     * Shared with LabourCostTransferExcelController so the PDF and the
     * workbook come from one load and one rule.
     *
     * @return array{transfer: LabourCostTransfer, summary: array, outletNames: \Illuminate\Support\Collection, company: ?Company}
     */
    protected function load(Request $request, int $id): array
    {
        $transfer = LabourCostTransfer::with([
            'lines' => fn ($q) => $q->orderBy('id'),
            'lines.fromOutlet', 'lines.employee', 'toOutlet', 'createdBy', 'confirmedBy',
        ])->findOrFail($id);

        $ends = array_merge([$transfer->to_outlet_id], $transfer->lines->pluck('from_outlet_id')->all());
        abort_unless((bool) array_intersect($request->user()->accessibleOutletIds(), $ends), 403);

        $summary     = LabourCostTransferCalculator::summaryByOutlet(LabourCostTransferCalculator::rowsFromTransfers([$transfer]));
        $outletNames = Outlet::withoutGlobalScopes()->whereIn('id', array_column($summary, 'outlet_id'))->pluck('name', 'id');
        $company     = Company::find($request->user()->company_id);

        return compact('transfer', 'summary', 'outletNames', 'company');
    }
}
