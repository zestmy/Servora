<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Supplier;

class SupplierNotificationService
{
    /**
     * Send PO notification to supplier based on their notification preference.
     */
    public static function notifyPo(PurchaseOrder $po): array
    {
        $supplier = $po->supplier;
        if (! $supplier) {
            return ['success' => false, 'message' => 'No supplier found.'];
        }

        $preference = $supplier->notification_preference ?? 'email';
        $results = [];

        // Email notification
        if (in_array($preference, ['email', 'both'])) {
            $emailResult = PoEmailService::sendApprovedPoEmail($po);
            $results[] = $emailResult;
        }

        // WhatsApp notification (placeholder)
        if (in_array($preference, ['whatsapp', 'both'])) {
            $whatsappResult = self::sendWhatsApp($supplier, $po);
            $results[] = $whatsappResult;
        }

        $allSuccess = collect($results)->every(fn ($r) => $r['success']);
        $messages = collect($results)->pluck('message')->implode('; ');

        return ['success' => $allSuccess, 'message' => $messages];
    }

    /**
     * WhatsApp notification placeholder.
     * TODO: Implement with Twilio/360dialog/Meta Cloud API when ready.
     */
    private static function sendWhatsApp(Supplier $supplier, PurchaseOrder $po): array
    {
        if (! $supplier->whatsapp_number) {
            return ['success' => false, 'message' => 'No WhatsApp number configured.'];
        }

        // Placeholder — log the intent
        \Illuminate\Support\Facades\Log::info("WhatsApp PO notification placeholder", [
            'supplier'    => $supplier->name,
            'whatsapp'    => $supplier->whatsapp_number,
            'po_number'   => $po->po_number,
            'total_amount' => $po->total_amount,
        ]);

        return ['success' => true, 'message' => 'WhatsApp notification queued (placeholder).'];
    }
}
