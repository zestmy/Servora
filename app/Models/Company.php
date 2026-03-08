<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'registration_number', 'slug', 'email', 'phone', 'address',
        'billing_address', 'logo', 'currency', 'is_active', 'require_po_approval',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'require_po_approval' => 'boolean',
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
}
