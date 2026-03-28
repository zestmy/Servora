<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class OutletPrepRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'outlet_id', 'kitchen_id', 'request_number',
        'status', 'needed_date', 'notes', 'created_by',
    ];

    protected $casts = ['needed_date' => 'date'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function kitchen(): BelongsTo { return $this->belongsTo(CentralKitchen::class, 'kitchen_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(OutletPrepRequestLine::class); }

    public static function generateNumber(): string
    {
        $prefix = 'PREP-' . Carbon::now()->format('Ymd') . '-';
        $latest = static::withoutGlobalScopes()
            ->where('request_number', 'like', "{$prefix}%")
            ->orderByDesc('request_number')
            ->value('request_number');
        $seq = $latest ? ((int) substr($latest, strrpos($latest, '-') + 1) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
