<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'brand_name', 'registration_number', 'slug', 'email', 'phone', 'address', 'timezone',
        'billing_address', 'logo', 'currency', 'tax_type', 'tax_percent',
        'show_price_on_do_grn', 'auto_generate_do', 'direct_supplier_order', 'po_cc_emails',
        'is_active', 'require_po_approval',
        'ordering_mode', 'require_pr_approval', 'default_tax_country', 'price_alert_threshold',
        'onboarding_completed_at', 'registered_via', 'trial_ends_at',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'require_po_approval'    => 'boolean',
        'tax_percent'            => 'decimal:2',
        'show_price_on_do_grn'   => 'boolean',
        'auto_generate_do'       => 'boolean',
        'direct_supplier_order'  => 'boolean',
        'require_pr_approval'    => 'boolean',
        'onboarding_completed_at' => 'datetime',
        'trial_ends_at'          => 'datetime',
    ];

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE])
            ->latestOfMany();
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isGrandfathered(): bool
    {
        return $this->registered_via === 'seeder' || $this->registered_via === 'admin';
    }

    public function cpus(): HasMany
    {
        return $this->hasMany(CentralPurchasingUnit::class);
    }

    public function isCpuMode(): bool
    {
        return $this->ordering_mode === 'cpu';
    }
}
