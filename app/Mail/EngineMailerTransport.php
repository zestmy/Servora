<?php

namespace App\Mail;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class EngineMailerTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.enginemailer.com/RESTAPI/V2/Submission/SendEmail';

    protected function doSend(SentMessage $message): void
    {
        $apiKey = AppSetting::get('enginemailer_api_key');

        if (!$apiKey) {
            Log::warning('EngineMailer transport: API key not configured, email not sent.');
            return;
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $toAddresses = [];
        foreach ($email->getTo() as $address) {
            $toAddresses[] = $address->getAddress();
        }

        $ccAddresses = [];
        foreach ($email->getCc() as $address) {
            $ccAddresses[] = $address->getAddress();
        }

        $senderEmail = AppSetting::get('enginemailer_sender_email');
        $senderName = 'Servora';

        if ($email->getFrom()) {
            $from = $email->getFrom()[0] ?? null;
            if ($from) {
                $senderEmail = $senderEmail ?: $from->getAddress();
                $senderName = $from->getName() ?: $senderName;
            }
        }

        $htmlContent = $email->getHtmlBody() ?? nl2br(e($email->getTextBody() ?? ''));

        $payload = [
            'ToEmail'          => implode(',', $toAddresses),
            'SenderEmail'      => $senderEmail,
            'SenderName'       => $senderName,
            'Subject'          => $email->getSubject() ?? '(No subject)',
            'SubmittedContent' => $htmlContent,
        ];

        if (!empty($ccAddresses)) {
            $payload['CCEmails'] = $ccAddresses;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['APIKey' => $apiKey])
                ->post(self::ENDPOINT, $payload);

            $body = $response->json();
            $statusCode = $body['Result']['StatusCode'] ?? null;

            if ((string) $statusCode !== '200') {
                $errorMsg = $body['Result']['ErrorMessage'] ?? 'Unknown error';
                Log::warning('EngineMailer transport failed', [
                    'to'    => $toAddresses,
                    'error' => $errorMsg,
                ]);
            } else {
                Log::info('EngineMailer transport sent', [
                    'to'  => $toAddresses,
                    'txn' => $body['Result']['TransactionID'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('EngineMailer transport exception', ['error' => $e->getMessage()]);
        }
    }

    public function __toString(): string
    {
        return 'enginemailer';
    }
}
