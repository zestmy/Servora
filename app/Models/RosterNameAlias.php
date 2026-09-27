<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "FARHAN on this outlet's roster sheet is this employee" — remembered from
 * the PDF import so the match is only ever made once.
 */
class RosterNameAlias extends Model
{
    protected $fillable = ['outlet_id', 'alias', 'employee_id'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** The form a sheet name is stored and looked up in: letters and digits only. */
    public static function key(string $name): string
    {
        return substr(preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($name)), 0, 100);
    }
}
