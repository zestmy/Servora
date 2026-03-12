<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DeliveryOrder;
use App\Models\GoodsReceivedNote;
use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseDocumentPdfController extends Controller
{
    public function __invoke(Request $request, string $type, int $id)
    {
        $company = Company::find(Auth::user()->company_id);

        return match ($type) {
            'po'  => $this->purchaseOrder($id, $company),
            'do'  => $this->deliveryOrder($id, $company),
            'grn' => $this->goodsReceivedNote($id, $company),
            default => abort(404),
        };
    }

    private function purchaseOrder(int $id, ?Company $company)
    {
        $po = PurchaseOrder::with(['outlet', 'supplier', 'lines.ingredient', 'lines.uom', 'createdBy', 'approvedBy'])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.purchase-order', compact('po', 'company'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("PO-{$po->po_number}.pdf");
    }

    private function deliveryOrder(int $id, ?Company $company)
    {
        $do = DeliveryOrder::with(['outlet', 'supplier', 'purchaseOrder', 'lines.ingredient', 'lines.uom', 'createdBy', 'receivedBy'])->findOrFail($id);
        $showPrice = (bool) $company?->show_price_on_do_grn;

        $pdf = Pdf::loadView('pdf.delivery-order', compact('do', 'company', 'showPrice'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("DO-{$do->do_number}.pdf");
    }

    private function goodsReceivedNote(int $id, ?Company $company)
    {
        $grn = GoodsReceivedNote::with(['outlet', 'supplier', 'deliveryOrder', 'purchaseOrder', 'lines.ingredient', 'lines.uom', 'receivedBy'])->findOrFail($id);
        $showPrice = (bool) $company?->show_price_on_do_grn;

        $pdf = Pdf::loadView('pdf.goods-received-note', compact('grn', 'company', 'showPrice'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("GRN-{$grn->grn_number}.pdf");
    }
}
