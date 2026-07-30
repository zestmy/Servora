<?php

namespace App\Services\Labels;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the PrintNode HTTP API.
 *
 * Deliberately thin, and deliberately the only place in the codebase that
 * knows PrintNode's wire format. Everything above it deals in "submit a PDF
 * to a printer and get a job id back", which is what makes the driver
 * swappable.
 *
 * Authentication is HTTP Basic with the API key as the username and an empty
 * password — PrintNode's scheme, not a mistake.
 *
 * Every method throws RuntimeException with a message fit to show a chef.
 * Callers decide whether that means "fall back to the browser" or "tell the
 * user their key is wrong".
 */
class PrintNodeClient
{
    public function __construct(private string $apiKey)
    {
        if (trim($apiKey) === '') {
            throw new RuntimeException('No PrintNode API key configured.');
        }
    }

    /** Verify a key. Returns the account description PrintNode reports. */
    public function whoami(): array
    {
        return $this->request()->get('/whoami')->throw()->json();
    }

    /**
     * Printers visible to this account.
     *
     * @return array<int, array{id: int, name: string, state: string, computer: string}>
     */
    public function printers(): array
    {
        $response = $this->request()->get('/printers');

        $this->guard($response);

        return collect($response->json())
            ->map(fn ($p) => [
                'id'       => (int) ($p['id'] ?? 0),
                'name'     => (string) ($p['name'] ?? 'Unnamed'),
                'state'    => (string) ($p['state'] ?? 'unknown'),
                'computer' => (string) ($p['computer']['name'] ?? ''),
            ])
            ->filter(fn ($p) => $p['id'] > 0)
            ->values()
            ->all();
    }

    /**
     * Queue a PDF for printing. Returns PrintNode's job id.
     *
     * fit_to_page is false for the same reason the browser path prints at
     * 100%: a label is an exact physical size and scaling it to the
     * printable area silently shrinks and clips it.
     *
     * Rotation goes through PrintNode's own job option rather than CSS,
     * because the PDF renderer cannot do transforms.
     */
    public function printPdf(
        int $printerId,
        string $pdf,
        string $title,
        bool $rotate = false
    ): string {
        $options = ['fit_to_page' => false];

        if ($rotate) {
            $options['rotate'] = 90;
        }

        $response = $this->request()->post('/printjobs', [
            'printerId'   => $printerId,
            'title'       => $title,
            'contentType' => 'pdf_base64',
            'content'     => base64_encode($pdf),
            'source'      => 'Servora labels',
            'options'     => $options,
        ]);

        $this->guard($response);

        // PrintNode answers with the bare job id.
        $jobId = $response->json();

        if (is_array($jobId)) {
            $jobId = $jobId['id'] ?? null;
        }

        if (! $jobId) {
            throw new RuntimeException('PrintNode accepted the job but returned no job id.');
        }

        return (string) $jobId;
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth($this->apiKey, '')
            ->baseUrl(rtrim((string) config('services.printnode.base_url'), '/'))
            ->timeout((int) config('services.printnode.timeout', 10))
            ->acceptJson()
            ->asJson();
    }

    /**
     * Turn transport failures into something a chef can act on.
     *
     * 401/403 is nearly always a wrong or revoked key, and saying so beats
     * a generic failure that sends someone hunting through the printer.
     */
    private function guard($response): void
    {
        if ($response->successful()) {
            return;
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException('PrintNode rejected the API key. Check it in Label Settings.');
        }

        throw new RuntimeException(sprintf(
            'PrintNode returned %d. %s',
            $response->status(),
            \Illuminate\Support\Str::limit(trim((string) $response->body()), 120)
        ));
    }
}
