<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CentralKitchen extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'code', 'outlet_id',
        'address', 'contact_person', 'email', 'phone', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kitchen_users', 'kitchen_id', 'user_id')
            ->withPivot('role')->withTimestamps();
    }

    public function productionOrders(): HasMany { return $this->hasMany(ProductionOrder::class, 'kitchen_id'); }

    public function scopeActive($query) { return $query->where('is_active', true); }
}
