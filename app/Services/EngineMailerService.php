<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EngineMailerService
{
    private const ENDPOINT = 'https://api.enginemailer.com/RESTAPI/Submission/SendEmail';

    /**
     * Check the EngineMailer API response for success.
     * EngineMailer returns HTTP 200 even on errors — the actual status is in the JSON body.
     */
    private static function parseResponse(\Illuminate\Http\Client\Response $response): array
    {
        if (!$response->successful()) {
            return ['success' => false, 'message' => 'HTTP error: ' . $response->status()];
        }

        $body = $response->json();
        $statusCode = $body['Result']['StatusCode'] ?? null;
        $errorMessage = $body['Result']['ErrorMessage'] ?? null;

        // EngineMailer uses StatusCode "200" for success in the body
        if ($statusCode !== null && (string) $statusCode !== '200') {
            $msg = $errorMessage ?: ('API returned status ' . $statusCode);
            return ['success' => false, 'message' => $msg];
        }

        return ['success' => true, 'message' => 'Email sent successfully.'];
    }

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
            $result = self::parseResponse($response);

            if ($result['success']) {
                $result['message'] = 'Test email sent successfully! Check your inbox at ' . $senderEmail . '.';
            }

            return $result;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Send a transactional email via EngineMailer REST API.
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
            $response = Http::timeout(30)->post(self::ENDPOINT, $payload);
            $result = self::parseResponse($response);

            if ($result['success']) {
                Log::info('EngineMailer email sent', ['to' => $toEmail, 'subject' => $subject]);
            } else {
                Log::warning('EngineMailer send failed', [
                    'to'      => $toEmail,
                    'message' => $result['message'],
                    'body'    => $response->body(),
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('EngineMailer exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Email send failed: ' . $e->getMessage()];
        }
    }
}
