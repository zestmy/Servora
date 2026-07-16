<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kitchen access granted via Settings > Users only wrote outlet_user rows (for
 * the kitchen's linked outlet) and users.default_kitchen_id — never the
 * kitchen_users pivot that the "Switch to Central Kitchen Mode" nav button,
 * the workspace switcher, and the kitchen.user middleware gate on. Users
 * granted CK access that way saw no Central Kitchen navigation at all.
 *
 * Backfill kitchen_users (role "staff") for:
 *  1. every (user, kitchen) pair implied by an outlet_user row on a kitchen's
 *     linked outlet, and
 *  2. any user with a default_kitchen_id but no pivot row for that kitchen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kitchens = DB::table('central_kitchens')
            ->whereNull('deleted_at')
            ->whereNotNull('outlet_id')
            ->get(['id', 'outlet_id']);

        foreach ($kitchens as $kitchen) {
            $userIds = DB::table('outlet_user')
                ->where('outlet_id', $kitchen->outlet_id)
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $this->ensurePivot((int) $kitchen->id, (int) $userId);
            }
        }

        $defaults = DB::table('users')
            ->whereNotNull('default_kitchen_id')
            ->get(['id', 'default_kitchen_id']);

        foreach ($defaults as $user) {
            $kitchenExists = DB::table('central_kitchens')
                ->where('id', $user->default_kitchen_id)
                ->whereNull('deleted_at')
                ->exists();
            if ($kitchenExists) {
                $this->ensurePivot((int) $user->default_kitchen_id, (int) $user->id);
            }
        }
    }

    private function ensurePivot(int $kitchenId, int $userId): void
    {
        $exists = DB::table('kitchen_users')
            ->where('kitchen_id', $kitchenId)
            ->where('user_id', $userId)
            ->exists();

        if (! $exists) {
            DB::table('kitchen_users')->insert([
                'kitchen_id' => $kitchenId,
                'user_id'    => $userId,
                'role'       => 'staff',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data backfill — not reversible.
    }
};
