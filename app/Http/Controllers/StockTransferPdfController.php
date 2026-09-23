<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OutletTransfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * One outlet stock transfer as a signable transfer note.
 *
 * Same access rule as opening the transfer on screen (TransferForm::mount):
 * anyone who can see either the sending or the receiving outlet.
 */
class StockTransferPdfController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        ['transfer' => $transfer, 'company' => $company] = $this->load($request, $id);

        return Pdf::loadView('pdf.stock-transfer', compact('transfer', 'company'))
            ->setPaper('a4', 'portrait')
            ->download('Stock-Transfer-' . $transfer->transfer_number . '.pdf');
    }

    /**
     * The transfer and its company, access-checked. Shared with
     * StockTransferExcelController so the note and the workbook are built
     * from the same load and the same rule.
     *
     * @return array{transfer: OutletTransfer, company: ?Company}
     */
    protected function load(Request $request, int $id): array
    {
        $transfer = OutletTransfer::with([
            'lines' => fn ($q) => $q->orderBy('id'),
            'lines.ingredient', 'lines.recipe', 'lines.uom', 'fromOutlet', 'toOutlet', 'createdBy',
        ])->findOrFail($id);

        $user = $request->user();
        abort_unless(
            $user->canAccessOutlet($transfer->from_outlet_id) || $user->canAccessOutlet($transfer->to_outlet_id),
            403
        );

        return ['transfer' => $transfer, 'company' => Company::find($user->company_id)];
    }
}
