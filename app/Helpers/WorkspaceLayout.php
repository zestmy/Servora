<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class WorkspaceLayout
{
    /**
     * Return the correct layout name based on the user's active workspace.
     */
    public static function get(): string
    {
        $user = Auth::user();
        if ($user && $user->activeWorkspace() === 'kitchen') {
            return 'layouts.kitchen';
        }
        return 'layouts.app';
    }
}
