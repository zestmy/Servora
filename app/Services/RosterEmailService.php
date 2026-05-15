<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Employee;
use App\Models\Roster;
use App\Models\RosterEmailRecipient;
use Illuminate\Support\Facades\Log;

class RosterEmailService
{
    /**
     * Send roster PDF to specified recipients.
     *
     * @param Roster $roster
     * @param bool $sendToEmployees Include all assigned employees
     * @param array $customRecipientIds IDs of RosterEmailRecipient to include
     * @param array $additionalEmails Additional email addresses
     * @return array Results with success count and errors
     */
    public static function send(
        Roster $roster,
        bool $sendToEmployees = true,
        array $customRecipientIds = [],
        array $additionalEmails = []
    ): array {
        $senderEmail = AppSetting::get('enginemailer_sender_email');
        if (!$senderEmail) {
            return ['success' => false, 'message' => 'Sender email not configured in API settings.'];
        }

        // Generate PDF
        $pdfOutput = RosterPdfService::generateOutput($roster);
        $periodLabel = $roster->week_start_date->format('M d') . ' - ' . $roster->week_end_date->format('M d, Y');
        $outletName = $roster->outlet?->name ?? 'Unknown Outlet';

        // Build email content
        $subject = "Duty Roster: {$outletName} - {$periodLabel}";
        $htmlContent = self::buildEmailContent($roster, $outletName, $periodLabel);

        // Prepare attachment
        $attachment = [
            'Content' => base64_encode($pdfOutput),
            'Filename' => "duty-roster-{$roster->week_start_date->format('Y-m-d')}.pdf",
            'MimeType' => 'application/pdf',
        ];

        // Collect all recipients
        $recipients = [];

        // 1. Assigned employees (if enabled)
        if ($sendToEmployees) {
            $employeeIds = $roster->entries->pluck('employee_id')->unique();
            $employees = Employee::whereIn('id', $employeeIds)->whereNotNull('email')->get();
            foreach ($employees as $emp) {
                if ($emp->email && filter_var($emp->email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[$emp->email] = $emp->name ?? $emp->email;
                }
            }
        }

        // 2. Custom recipients (from RosterEmailRecipient)
        if (!empty($customRecipientIds)) {
            $customRecipients = RosterEmailRecipient::whereIn('id', $customRecipientIds)->active()->get();
            foreach ($customRecipients as $recipient) {
                $recipients[$recipient->email] = $recipient->name ?? $recipient->email;
            }
        }

        // 3. Additional emails
        foreach ($additionalEmails as $email) {
            $email = trim($email);
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[$email] = $email;
            }
        }

        if (empty($recipients)) {
            return ['success' => false, 'message' => 'No valid recipients found.'];
        }

        // Send emails
        $results = [
            'success' => true,
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($recipients as $email => $name) {
            $result = EngineMailerService::send(
                toEmail: $email,
                senderEmail: $senderEmail,
                senderName: 'Servora',
                subject: $subject,
                htmlContent: $htmlContent,
                cc: [],
                attachments: [$attachment]
            );

            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "{$email}: {$result['message']}";
            }
        }

        $results['message'] = "Sent to {$results['sent']} recipient(s).";
        if ($results['failed'] > 0) {
            $results['message'] .= " {$results['failed']} failed.";
        }

        Log::info('Roster email sent', [
            'roster_id' => $roster->id,
            'sent' => $results['sent'],
            'failed' => $results['failed'],
        ]);

        return $results;
    }

    /**
     * Build HTML content for the email body.
     */
    protected static function buildEmailContent(Roster $roster, string $outletName, string $periodLabel): string
    {
        $sectionName = $roster->section?->name ?? 'All Sections';
        $statusLabel = ucfirst($roster->status);

        return <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <h2 style="color: #4f46e5; margin-bottom: 20px;">Duty Roster</h2>

    <table style="width: 100%; margin-bottom: 20px; font-size: 14px;">
        <tr>
            <td style="padding: 8px 0; color: #666;">Outlet:</td>
            <td style="padding: 8px 0; font-weight: bold;">{$outletName}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Section:</td>
            <td style="padding: 8px 0;">{$sectionName}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Period:</td>
            <td style="padding: 8px 0; font-weight: bold;">{$periodLabel}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #666;">Status:</td>
            <td style="padding: 8px 0;">
                <span style="display: inline-block; padding: 4px 12px; background: #dcfce7; color: #166534; border-radius: 12px; font-size: 12px;">
                    {$statusLabel}
                </span>
            </td>
        </tr>
    </table>

    <p style="color: #666; font-size: 14px; line-height: 1.6;">
        Please find attached the duty roster for the period mentioned above.
        Review your assigned shifts and contact your supervisor if you have any questions.
    </p>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;">

    <p style="font-size: 12px; color: #9ca3af;">
        This email was sent automatically by Servora. Do not reply to this email.
    </p>
</div>
HTML;
    }
}
