<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutletTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'from_outlet_id', 'to_outlet_id', 'transfer_number',
        'status', 'transfer_date', 'notes', 'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fromOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'from_outlet_id');
    }

    public function toOutlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'to_outlet_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OutletTransferLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
