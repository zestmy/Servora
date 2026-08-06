<?php

namespace App\Mail;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * A FAILURE HERE MUST THROW.
 *
 * This used to log a warning and return — for a missing API key, for a non-200
 * from the provider, and for a network exception. Mail::send() then returned
 * normally and every caller recorded a success, so `payslip_deliveries` and
 * `leave_notifications` rows read "sent" for email that was never delivered.
 * "Did my manager get told?" had a confident answer and the wrong one.
 *
 * Symfony's contract is that a transport throws TransportException when it
 * cannot deliver, and both queued callers already catch it, record the reason
 * on the row and let the queue retry. Silence is the one outcome nobody can act on.
 */
class EngineMailerTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.enginemailer.com/RESTAPI/V2/Submission/SendEmail';

    protected function doSend(SentMessage $message): void
    {
        $apiKey = AppSetting::get('enginemailer_api_key');

        if (!$apiKey) {
            throw new TransportException(
                'EngineMailer API key is not configured — set it in application settings.'
            );
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
        } catch (TransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Connection refused, DNS failure, timeout. Retryable, and the
            // queue will retry it — but only if it hears about it.
            Log::error('EngineMailer transport exception', ['error' => $e->getMessage()]);

            throw new TransportException(
                'Could not reach EngineMailer: ' . $e->getMessage(), 0, $e
            );
        }

        if ((string) $statusCode !== '200') {
            $errorMsg = $body['Result']['ErrorMessage'] ?? 'Unknown error';

            Log::warning('EngineMailer transport failed', [
                'to'     => $toAddresses,
                'status' => $statusCode,
                'error'  => $errorMsg,
            ]);

            throw new TransportException(
                'EngineMailer rejected the message (' . ($statusCode ?? 'no status') . '): ' . $errorMsg
            );
        }

        Log::info('EngineMailer transport sent', [
            'to'  => $toAddresses,
            'txn' => $body['Result']['TransactionID'] ?? null,
        ]);
    }

    public function __toString(): string
    {
        return 'enginemailer';
    }
}
