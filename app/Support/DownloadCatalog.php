<?php

namespace App\Support;

use App\Models\PosAgent;
use App\Models\PrintAgent;

/**
 * The catalog behind both downloads pages — Settings › Files & Downloads
 * and the public /downloads page.
 *
 * Entries are DATA, one per installable tool, resolved at render time
 * against public/downloads/ where the built zips are hosted untracked (the
 * same arrangement PrintAgent::downloadUrl() has always used: the file's
 * presence on the server IS the publish switch, so a page can never offer a
 * link that 404s). Versions come from the models' CURRENT_VERSION
 * constants, which drift tests pin to the Go sources.
 */
class DownloadCatalog
{
    /**
     * @return array<int, array{
     *   key: string, name: string, tagline: string, description: string,
     *   version: string, stage: string, icon: string,
     *   requirements: array<int, string>, steps: array<int, string>,
     *   files: array<int, array{label: string, filename: string, url: string, size: string, updated: string}>
     * }>
     */
    public static function entries(): array
    {
        return array_map(
            fn (array $entry) => $entry + ['files' => self::resolveFiles($entry['filenames'])],
            self::definitions()
        );
    }

    /** Entries that have at least one hosted file. */
    public static function available(): array
    {
        return array_values(array_filter(self::entries(), fn (array $e) => $e['files'] !== []));
    }

    private static function definitions(): array
    {
        return [
            [
                'key'         => 'pos-agent',
                'name'        => 'POS Sync Agent',
                'tagline'     => 'Sales data from your POS terminals, synced automatically.',
                'description' => 'Runs as a Windows service on the POS terminal, watches the folder your '
                    . 'POS exports its sales reports into, and uploads anything new to Servora — no more '
                    . 'exporting the Excel file and uploading it by hand. Unsure where your POS keeps its '
                    . 'data? The bundled "discover" command inventories the machine first.',
                'version'     => PosAgent::CURRENT_VERSION,
                'stage'       => version_compare(PosAgent::CURRENT_VERSION, '1.0.0', '<') ? 'Discovery preview' : 'Stable',
                'icon'        => 'display',
                'requirements' => [
                    'Windows 10 or newer on the POS terminal',
                    'A pairing code from Sales › POS Sync, issued by a manager',
                    'The folder the POS exports its reports into (run "discover" if unsure)',
                    'Pick 64-bit first; if Windows refuses to run it, use the 32-bit build',
                ],
                'steps' => [
                    'Extract the zip on the POS terminal',
                    'Double-click SETUP.cmd and enter the server address, pairing code and export folder',
                    'The service starts automatically — check the agent shows Online under Sales › POS Sync',
                    'Export a report from the POS; it appears under Sales › POS Sync within the hour',
                ],
                'filenames' => [
                    '64-bit' => 'servora-pos-agent-' . PosAgent::CURRENT_VERSION . '-windows-amd64.zip',
                    '32-bit' => 'servora-pos-agent-' . PosAgent::CURRENT_VERSION . '-windows-386.zip',
                ],
            ],
            [
                'key'         => 'print-agent',
                'name'        => 'Print Agent',
                'tagline'     => 'Label printing from Servora to the printers on an outlet PC.',
                'description' => 'Runs as a Windows service on the outlet PC, pairs with Servora once, '
                    . 'and prints food safety labels sent from the Label app to your local label '
                    . 'printers — no cloud printing account needed.',
                'version'     => PrintAgent::CURRENT_VERSION,
                'stage'       => 'Stable',
                'icon'        => 'printer',
                'requirements' => [
                    'Windows 10 or newer, 64-bit',
                    'Administrator rights (it installs as a Windows service)',
                    'A pairing code from Labels › Agents, issued by a manager',
                ],
                'steps' => [
                    'Extract the zip on the PC connected to your label printer',
                    'Double-click SETUP.cmd and enter your server address and pairing code',
                    'The service starts automatically — check the agent shows Online under Labels › Agents',
                ],
                'filenames' => [
                    '64-bit' => 'servora-print-agent-' . PrintAgent::CURRENT_VERSION . '-windows-amd64.zip',
                ],
            ],
        ];
    }

    private static function resolveFiles(array $filenames): array
    {
        $files = [];

        foreach ($filenames as $label => $filename) {
            $path = public_path('downloads/' . $filename);

            if (! file_exists($path)) {
                continue;
            }

            $files[] = [
                'label'    => $label,
                'filename' => $filename,
                'url'      => asset('downloads/' . $filename),
                'size'     => self::humanSize((int) filesize($path)),
                'updated'  => date('j M Y', (int) filemtime($path)),
            ];
        }

        return $files;
    }

    private static function humanSize(int $bytes): string
    {
        if ($bytes >= 1 << 20) {
            return number_format($bytes / (1 << 20), 1) . ' MB';
        }

        return number_format(max(1, $bytes >> 10)) . ' KB';
    }
}
