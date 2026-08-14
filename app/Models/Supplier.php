<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'code', 'contact_person', 'email', 'phone', 'address', 'payment_terms', 'is_active',
        'whatsapp_number', 'notification_preference', 'portal_enabled',
        'tax_registration_number', 'billing_address', 'bank_name', 'bank_account_number',
        'city', 'state', 'country',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'portal_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'supplier_ingredients')
            ->withPivot(['supplier_sku', 'last_cost', 'uom_id', 'is_preferred'])
            ->withTimestamps();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(SupplierUser::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * Suppliers a picker may offer: the active ones, plus whatever this record
     * already points at.
     *
     * Same rule and same reason as Outlet::selectable(), whose docblock has
     * the long version: a dropdown filtered on is_active stops containing the
     * value of the record it is editing the moment that supplier is retired,
     * and the browser then highlights a different one.
     *
     * @param  array<int, int|string|null>|int|string|null  $keep  ids already on the record
     */
    public function scopeSelectable($query, $keep = [])
    {
        $keep = collect(is_array($keep) ? $keep : [$keep])
            ->filter()->map(fn ($id) => (int) $id)->all();

        return $query->where(function ($q) use ($keep) {
            $q->where('is_active', true);

            if ($keep) {
                $q->orWhereIn('id', $keep);
            }
        });
    }
}
