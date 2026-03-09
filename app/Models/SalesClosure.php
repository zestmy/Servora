<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesClosure extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'closure_date', 'reason', 'notes', 'created_by',
    ];

    protected $casts = [
        'closure_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function outlet(): BelongsTo   { return $this->belongsTo(Outlet::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public static function commonReasons(): array
    {
        return [
            'Closed for Renovation',
            'Closed for Public Holiday',
            'Closed for Hari Raya',
            'Closed for Chinese New Year',
            'Closed for Deepavali',
            'Closed for Christmas',
            'Closed for Maintenance',
            'Closed for Private Event',
            'Closed for Staff Training',
            'Closed due to Weather',
            'No Sales Recorded',
        ];
    }
}
