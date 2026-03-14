<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['company_id', 'name', 'form_type', 'description', 'sort_order', 'is_active', 'supplier_id', 'receiver_name', 'department_id'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FormTemplateLine::class)->orderBy('sort_order')->orderBy('id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('form_type', $type);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public static function formTypeOptions(): array
    {
        return [
            'stock_take'     => 'Stock Take',
            'purchase_order' => 'Purchase Order',
            'wastage'        => 'Wastage',
        ];
    }

    public function formTypeLabel(): string
    {
        return static::formTypeOptions()[$this->form_type] ?? $this->form_type;
    }
}
