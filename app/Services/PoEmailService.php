<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PoEmailService
{
    /**
     * Send approved PO email to supplier, approver, receiver and CC list.
     */
    public static function sendApprovedPoEmail(PurchaseOrder $po): array
    {
        $po->loadMissing(['supplier', 'outlet', 'department', 'lines.ingredient', 'lines.uom', 'createdBy', 'approvedBy']);

        $company = Company::find($po->company_id);
        if (! $company) {
            return ['success' => false, 'message' => 'Company not found.'];
        }

        $supplierEmail = $po->supplier?->email;
        if (! $supplierEmail) {
            return ['success' => false, 'message' => 'Supplier has no email address.'];
        }

        // Build sender info
        $senderEmail = AppSetting::get('enginemailer_sender_email', $company->email ?? 'noreply@servora.com.my');
        $senderName  = $company->name ?? 'Servora';

        // Build CC list
        $ccEmails = [];

        // Approver email
        if ($po->approvedBy?->email) {
            $ccEmails[] = $po->approvedBy->email;
        }

        // Receiver email (look up user by name if receiver_name is set)
        if ($po->receiver_name) {
            $receiverUser = User::where('company_id', $po->company_id)
                ->where('name', $po->receiver_name)
                ->first();
            if ($receiverUser?->email) {
                $ccEmails[] = $receiverUser->email;
            }
        }

        // Creator email
        if ($po->createdBy?->email) {
            $ccEmails[] = $po->createdBy->email;
        }

        // Company CC list from settings
        $companyCcList = $company->po_cc_emails ?? '';
        if ($companyCcList) {
            $extraCc = array_map('trim', explode(',', $companyCcList));
            $extraCc = array_filter($extraCc, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
            $ccEmails = array_merge($ccEmails, $extraCc);
        }

        // De-duplicate and remove supplier email from CC
        $ccEmails = array_unique(array_filter($ccEmails));
        $ccEmails = array_values(array_diff($ccEmails, [$supplierEmail]));

        // Generate PO PDF
        $pdf = Pdf::loadView('pdf.purchase-order', compact('po', 'company'))
            ->setPaper('a4', 'portrait');
        $pdfContent = $pdf->output();

        // Build HTML email body
        $html = self::buildEmailHtml($po, $company);

        // Build attachment
        $attachments = [
            [
                'FileName'    => "PO-{$po->po_number}.pdf",
                'FileContent' => base64_encode($pdfContent),
                'ContentType' => 'application/pdf',
            ],
        ];

        $subject = "Purchase Order {$po->po_number} — {$company->name}";

        $result = EngineMailerService::send(
            $supplierEmail,
            $senderEmail,
            $senderName,
            $subject,
            $html,
            $ccEmails,
            $attachments
        );

        if ($result['success']) {
            Log::info('PO email sent', ['po' => $po->po_number, 'to' => $supplierEmail, 'cc' => $ccEmails]);
        }

        return $result;
    }

    private static function buildEmailHtml(PurchaseOrder $po, Company $company): string
    {
        $outletName = $po->outlet?->name ?? '';
        $supplierName = $po->supplier?->name ?? '';
        $orderDate = $po->order_date?->format('d M Y') ?? '';
        $expectedDelivery = $po->expected_delivery_date?->format('d M Y') ?? 'Not specified';
        $total = number_format($po->total_amount, 2);
        $currency = $company->currency ?? 'RM';
        $itemCount = $po->lines->count();
        $companyName = $company->name ?? 'Company';
        $receiver = $po->receiver_name ?? '';
        $department = $po->department?->name ?? '';

        $deliverTo = $outletName;
        if ($receiver) $deliverTo .= "<br>Attn: {$receiver}";
        if ($department) $deliverTo .= "<br>Dept: {$department}";

        return <<<HTML
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
            <div style="background: #4f46e5; color: #fff; padding: 20px 24px; border-radius: 8px 8px 0 0;">
                <h1 style="margin: 0; font-size: 20px;">{$companyName}</h1>
                <p style="margin: 4px 0 0; font-size: 14px; opacity: 0.9;">Purchase Order Notification</p>
            </div>

            <div style="padding: 24px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 8px 8px;">
                <p style="font-size: 14px; margin: 0 0 16px;">
                    Dear <strong>{$supplierName}</strong>,
                </p>
                <p style="font-size: 14px; margin: 0 0 20px;">
                    Please find attached Purchase Order <strong>{$po->po_number}</strong> for your processing.
                </p>

                <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 8px 12px; background: #f9fafb; border: 1px solid #e5e7eb; font-weight: 600; width: 140px;">PO Number</td>
                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{$po->po_number}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 12px; background: #f9fafb; border: 1px solid #e5e7eb; font-weight: 600;">Order Date</td>
                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{$orderDate}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 12px; background: #f9fafb; border: 1px solid #e5e7eb; font-weight: 600;">Expected Delivery</td>
                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{$expectedDelivery}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 12px; background: #f9fafb; border: 1px solid #e5e7eb; font-weight: 600;">Deliver To</td>
                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{$deliverTo}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 12px; background: #f9fafb; border: 1px solid #e5e7eb; font-weight: 600;">Items</td>
                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{$itemCount} items</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 12px; background: #f9fafb; border: 1px solid #e5e7eb; font-weight: 600;">Total Amount</td>
                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; font-weight: 700; font-size: 15px;">{$currency} {$total}</td>
                    </tr>
                </table>

                <p style="font-size: 13px; color: #6b7280; margin: 0 0 8px;">
                    Please confirm receipt of this order and arrange delivery as per the expected date.
                </p>

                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">

                <p style="font-size: 11px; color: #9ca3af; margin: 0;">
                    This is an automated email from {$companyName} via Servora.
                </p>
            </div>
        </div>
        HTML;
    }
}
