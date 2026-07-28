<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * kitchen_users.role (manager|chef|staff) has existed since the module was
     * built but was hardcoded to 'staff' on every assignment and read nowhere,
     * so every kitchen member has had unrestricted rights — including Fulfil,
     * which moves real stock out of the kitchen.
     *
     * Now that the role actually gates those actions, existing members are
     * promoted to 'manager' so nobody loses access they have today. Admins can
     * demote from Settings > Kitchen Management; new members default to staff.
     */
    public function up(): void
    {
        DB::table('kitchen_users')->where('role', 'staff')->update(['role' => 'manager']);
    }

    public function down(): void
    {
        // The pre-split state: everyone was 'staff' in the column and
        // unrestricted in practice.
        DB::table('kitchen_users')->where('role', 'manager')->update(['role' => 'staff']);
    }
};
