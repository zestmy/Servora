<?php

namespace App\Livewire\Marketing;

use App\Support\DownloadCatalog;
use Livewire\Component;

/**
 * Public /downloads — the same catalog Settings › Files & Downloads reads,
 * minus entries with nothing hosted. Public because the person standing at
 * an outlet PC extracting a zip usually has no Servora login; the zips are
 * public assets already, and nothing works until a manager pairs it.
 */
class Downloads extends Component
{
    public function render()
    {
        return view('livewire.marketing.downloads', [
            'entries' => DownloadCatalog::available(),
        ])->layout('layouts.marketing', [
            'title'       => 'Downloads — Servora companion apps',
            'description' => 'Download the Servora companion apps for your outlet PCs and POS terminals: label printing and POS sales sync for Windows.',
        ]);
    }
}
