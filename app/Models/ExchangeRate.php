<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The last good Bank Negara rate for a currency: MYR per `unit` of it. */
class ExchangeRate extends Model
{
    public $timestamps = false;

    protected $fillable = ['currency', 'unit', 'buying_rate', 'selling_rate', 'middle_rate', 'rate_date', 'fetched_at'];

    protected $casts = [
        'unit'         => 'integer',
        'buying_rate'  => 'float',
        'selling_rate' => 'float',
        'middle_rate'  => 'float',
        'rate_date'    => 'date',
        'fetched_at'   => 'datetime',
    ];
}
