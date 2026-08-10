<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code emailed to an employee so they can sign in without a PIN.
 *
 * No CompanyScope: these are read while nobody is authenticated, so the scope
 * would have nothing to resolve from. Reached only through the employee, whose
 * company is matched explicitly by the service that issues them.
 *
 * `code_hash` is never compared in PHP — see EmailLoginCode::verify(), which
 * uses Hash::check so a leaked row cannot be turned back into a sign-in.
 */
class EmployeeLoginCode extends Model
{
    protected $fillable = [
        'employee_id', 'code_hash', 'expires_at', 'consumed_at', 'attempts', 'requested_ip',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
        'attempts'    => 'integer',
    ];

    /** Hidden for the same reason a password is: it is a live credential. */
    protected $hidden = ['code_hash'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
