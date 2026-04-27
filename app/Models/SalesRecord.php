<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'reference_number', 'sale_date',
        'total_revenue', 'total_cost', 'notes', 'pax', 'meal_period', 'created_by',
    ];

    protected $casts = [
        'sale_date'     => 'date',
        'total_revenue' => 'decimal:4',
        'total_cost'    => 'decimal:4',
        'pax'           => 'integer',
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

    public function lines(): HasMany
    {
        return $this->hasMany(SalesRecordLine::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SalesRecordAttachment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function mealPeriodOptions(): array
    {
        return [
            'all_day'   => 'All Day',
            'breakfast' => 'Breakfast',
            'lunch'     => 'Lunch',
            'tea_time'  => 'Tea Time',
            'dinner'    => 'Dinner',
            'supper'    => 'Supper',
        ];
    }

    public function mealPeriodLabel(): string
    {
        return static::mealPeriodOptions()[$this->meal_period ?? 'all_day'] ?? ucfirst($this->meal_period ?? '');
    }
}
