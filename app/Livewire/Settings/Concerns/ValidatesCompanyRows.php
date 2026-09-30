<?php

namespace App\Livewire\Settings\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation rules for ids that arrive from the browser and must belong to the
 * ACTIVE company. A bare `exists:outlets,id` passes for any company's outlet, so
 * an approver, target or group could be pointed across tenants by editing the
 * request.
 */
trait ValidatesCompanyRows
{
    /** `exists` on a table carrying company_id, bounded to the active company. */
    protected function companyRowRule(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('company_id', Auth::user()?->company_id);
    }

    /**
     * The user is a member of the active company. Membership is the company_user
     * pivot; users.company_id is only the active-company pointer, but it is
     * accepted too so users that predate the pivot are not rejected.
     */
    protected function companyUserRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $companyId = Auth::user()?->company_id;
            $id = (int) $value;

            $member = $companyId && $id && (
                DB::table('company_user')->where('company_id', $companyId)->where('user_id', $id)->exists()
                || DB::table('users')->where('id', $id)->where('company_id', $companyId)->exists()
            );

            if (! $member) {
                $fail('The selected :attribute is invalid.');
            }
        };
    }
}
