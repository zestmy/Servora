<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'section_id', 'staff_id',
        'name', 'designation',
        'email', 'phone', 'is_active',
        'join_date', 'food_handler_certified', 'typhoid_card',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'join_date'              => 'date',
        'food_handler_certified' => 'boolean',
        'typhoid_card'           => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
