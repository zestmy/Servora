<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Ends an impersonation session started from Admin > Users: restores the
 * original system admin login stored in the session. This route must NOT
 * sit behind SystemAdminOnly — while impersonating, the authenticated user
 * is the (non-admin) target; the pulled impersonator id is the authority.
 */
class ImpersonationController extends Controller
{
    public function stop(Request $request)
    {
        $adminId = $request->session()->pull('impersonator_id');
        abort_unless($adminId, 403);

        $admin = User::find($adminId);
        abort_unless($admin && $admin->isSystemRole(), 403);

        Log::info('Impersonation ended', [
            'admin_id'  => $admin->id,
            'target_id' => Auth::id(),
        ]);

        // Impersonated user's outlet / workspace state must not leak back.
        $request->session()->forget(['active_outlet_id', 'workspace_mode', 'active_kitchen_id']);
        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.users')->with('success', 'Returned to your admin account.');
    }
}
