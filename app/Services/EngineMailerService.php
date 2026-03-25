<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EngineMailerService
{
    private const ENDPOINT = 'https://api.enginemailer.com/RESTAPI/Submission/SendEmail';

    /**
     * Test the EngineMailer API connection by sending a test email.
     */
    public static function testConnection(string $apiKey, string $senderEmail): array
    {
        $payload = [
            'UserKey'          => $apiKey,
            'ToEmail'          => $senderEmail,
            'SenderEmail'      => $senderEmail,
            'SenderName'       => 'Servora',
            'Subject'          => 'Servora — EngineMailer Test Connection',
            'SubmittedContent' => '<p>This is a test email from Servora to verify your EngineMailer API connection is working correctly.</p><p>If you received this email, your API key and sender email are configured correctly.</p>',
        ];

        try {
            $response = Http::timeout(30)->post(self::ENDPOINT, $payload);

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Test email sent successfully! Check your inbox at ' . $senderEmail . '.'];
            }

            return ['success' => false, 'message' => 'API error: ' . $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Send a transactional email via EngineMailer REST API.
     *
     * @param string      $toEmail        Recipient email
     * @param string      $senderEmail    From email
     * @param string      $senderName     From name
     * @param string      $subject        Email subject
     * @param string      $htmlContent    HTML body
     * @param array       $cc             CC email addresses
     * @param array       $attachments    [{FileName, FileContent (base64), ContentType}]
     * @return array      [success => bool, message => string]
     */
    public static function send(
        string $toEmail,
        string $senderEmail,
        string $senderName,
        string $subject,
        string $htmlContent,
        array  $cc = [],
        array  $attachments = []
    ): array {
        $apiKey = AppSetting::get('enginemailer_api_key');

        if (! $apiKey) {
            return ['success' => false, 'message' => 'EngineMailer API key not configured.'];
        }

        $payload = [
            'UserKey'          => $apiKey,
            'ToEmail'          => $toEmail,
            'SenderEmail'      => $senderEmail,
            'SenderName'       => $senderName,
            'Subject'          => $subject,
            'SubmittedContent' => $htmlContent,
        ];

        if (! empty($cc)) {
            $payload['CCEmail'] = implode(',', $cc);
        }

        if (! empty($attachments)) {
            $payload['Attachments'] = $attachments;
        }

        try {
            $response = Http::timeout(30)
                ->post(self::ENDPOINT, $payload);

            if ($response->successful()) {
                Log::info('EngineMailer email sent', ['to' => $toEmail, 'subject' => $subject]);
                return ['success' => true, 'message' => 'Email sent successfully.'];
            }

            Log::warning('EngineMailer send failed', [
                'to'     => $toEmail,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return ['success' => false, 'message' => 'EngineMailer API error: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('EngineMailer exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Email send failed: ' . $e->getMessage()];
        }
    }
}
