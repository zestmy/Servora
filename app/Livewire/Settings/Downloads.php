<?php

namespace App\Livewire\Settings;

use App\Support\DownloadCatalog;
use Livewire\Component;

/**
 * Settings › Files & Downloads — the installable companion tools.
 *
 * Deliberately ungated: the person extracting a zip on an outlet PC is
 * often not the person who administers anything, and the files themselves
 * are public assets. What IS privileged — issuing a pairing code — stays
 * behind its own screen's permission.
 */
class Downloads extends Component
{
    public function render()
    {
        return view('livewire.settings.downloads', [
            'entries' => DownloadCatalog::entries(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Files & Downloads']);
    }
}
