<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\SopExport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SopExportController extends Controller
{
    /**
     * Hand over a finished queued export.
     *
     * The file lives on the private disk, not public storage: an SOP handbook
     * is the company's recipe book, and a public path would be a guessable URL
     * to all of it. Everything is checked here instead — the row belongs to the
     * caller's company, it actually finished, and the file is still on disk
     * (pruning runs on a schedule and can have been there first).
     */
    public function download(SopExport $export)
    {
        abort_unless($export->company_id === Auth::user()->company_id, 404);

        if (! $export->isDownloadable()) {
            // 404 rather than 410: from the outside "expired" and "never
            // finished" are the same thing, and the panel already explains
            // which one it was.
            throw new NotFoundHttpException('That export is no longer available.');
        }

        return Storage::disk('local')->download(
            $export->file_path,
            $export->filename ?: 'SOPs.pdf'
        );
    }
}
