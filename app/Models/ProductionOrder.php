<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ProductionOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'kitchen_id', 'order_number', 'status',
        'production_date', 'needed_by_date', 'notes',
        'created_by', 'approved_by', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'production_date' => 'date',
        'needed_by_date'  => 'date',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function kitchen(): BelongsTo { return $this->belongsTo(CentralKitchen::class, 'kitchen_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function lines(): HasMany { return $this->hasMany(ProductionOrderLine::class); }
    public function logs(): HasMany { return $this->hasMany(ProductionLog::class); }

    public static function generateNumber(): string
    {
        $prefix = 'PROD-' . Carbon::now()->format('Ymd') . '-';
        $latest = static::withoutGlobalScopes()
            ->where('order_number', 'like', "{$prefix}%")
            ->orderByDesc('order_number')
            ->value('order_number');
        $seq = $latest ? ((int) substr($latest, strrpos($latest, '-') + 1) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
