<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class QuotationRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'rfq_number', 'title', 'status',
        'needed_by_date', 'notes', 'created_by',
    ];

    protected $casts = [
        'needed_by_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(QuotationRequestLine::class); }
    public function suppliers(): HasMany { return $this->hasMany(QuotationRequestSupplier::class); }
    public function quotations(): HasMany { return $this->hasMany(SupplierQuotation::class); }

    public static function generateNumber(): string
    {
        $prefix = 'RFQ-' . Carbon::now()->format('Ymd') . '-';
        $latest = static::withoutGlobalScopes()
            ->where('rfq_number', 'like', "{$prefix}%")
            ->orderByDesc('rfq_number')
            ->value('rfq_number');
        $seq = $latest ? ((int) substr($latest, strrpos($latest, '-') + 1) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
