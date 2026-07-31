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
                // The driver's own paper/form names. Without naming one on
                // the job, PrintNode falls back to the driver default — and
                // if that default isn't the label stock, the page gets
                // rotated or scaled to fit it.
                'papers'   => $this->papers($p),
            ])
            ->filter(fn ($p) => $p['id'] > 0)
            ->values()
            ->all();
    }

    /**
     * Paper names a printer offers, with size in mm where reported.
     *
     * PrintNode gives capabilities.papers as name => [width, height] in
     * MICRONS, and sometimes null for sizes it can't measure. Parsed
     * defensively: an odd shape here should cost the size hint, not the
     * whole printer list.
     *
     * @return array<int, array{name: string, size: string|null}>
     */
    private function papers(array $printer): array
    {
        $papers = $printer['capabilities']['papers'] ?? null;

        if (! is_array($papers)) {
            return [];
        }

        $out = [];

        foreach ($papers as $name => $dims) {
            $size = null;

            if (is_array($dims) && isset($dims[0], $dims[1]) && $dims[0] && $dims[1]) {
                $size = sprintf('%.0f × %.0f mm', $dims[0] / 1000, $dims[1] / 1000);
            }

            $out[] = ['name' => (string) $name, 'size' => $size];
        }

        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $out;
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
        bool $rotate = false,
        ?string $paper = null
    ): string {
        $options = ['fit_to_page' => false];

        // Name the paper rather than trusting the driver default. The job
        // goes through the Windows driver on the client machine, and if its
        // default form is not the label stock — 4x6 or A4, say — the page is
        // rotated or scaled to fit it before it ever reaches the printer.
        if ($paper) {
            $options['paper'] = $paper;
        }

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

    /**
     * Latest reported state for each job.
     *
     * PrintNode groups states per job and returns the history, so the last
     * entry is the current one. The response shape has been seen both as a
     * flat list of state objects and as a list-of-lists grouped by job, so
     * this flattens defensively rather than assuming — a reconciler that
     * throws on an unexpected shape would leave every job stuck at 'queued'.
     *
     * @param  array<int, string>  $jobIds
     * @return array<string, string> job id => state
     */
    public function jobStates(array $jobIds): array
    {
        $jobIds = array_values(array_filter(array_map('strval', $jobIds)));

        if (! $jobIds) {
            return [];
        }

        $response = $this->request()->get('/printjobs/' . implode(',', $jobIds) . '/states');

        $this->guard($response);

        $states = [];

        foreach ($this->flatten($response->json() ?? []) as $entry) {
            $jobId = $entry['printJobId'] ?? null;
            $state = $entry['state'] ?? null;

            if ($jobId && $state) {
                // Later entries overwrite earlier ones: history in order.
                $states[(string) $jobId] = (string) $state;
            }
        }

        return $states;
    }

    /** One level of nesting, or none. Both shapes end up as state rows. */
    private function flatten(array $payload): array
    {
        $out = [];

        foreach ($payload as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['state']) || isset($item['printJobId'])) {
                $out[] = $item;

                continue;
            }

            foreach ($item as $inner) {
                if (is_array($inner)) {
                    $out[] = $inner;
                }
            }
        }

        return $out;
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
