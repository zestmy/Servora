<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZeoniqDepartmentMapping extends Model
{
    protected $fillable = [
        'company_id',
        'zeoniq_department_name',
        'sales_category_id',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function salesCategory(): BelongsTo
    {
        return $this->belongsTo(SalesCategory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
