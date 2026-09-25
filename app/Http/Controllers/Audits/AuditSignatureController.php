<?php

namespace App\Http\Controllers\Audits;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Serves an audit's acknowledgement signature from the private disk.
 *
 * A signature is not a photograph of a grease trap: it goes on `local`, not
 * `public`, and comes back only to somebody who may see the audit it signed.
 */
class AuditSignatureController extends Controller
{
    public function __invoke(int $id)
    {
        $audit = Audit::findOrFail($id);

        abort_unless(Auth::user()->canAccessOutlet($audit->outlet_id), 403);
        abort_unless($audit->signature_path && Storage::disk('local')->exists($audit->signature_path), 404);

        return Storage::disk('local')->response($audit->signature_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
