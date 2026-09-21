<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormTemplateLine extends Model
{
    protected $fillable = [
        'form_template_id', 'item_type', 'ingredient_id', 'recipe_id', 'asset_id',
        'default_quantity', 'sort_order',
    ];

    protected $casts = [
        'default_quantity' => 'float',
        'sort_order'       => 'integer',
    ];

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function itemName(): string
    {
        return match ($this->item_type) {
            'recipe' => $this->recipe?->name ?? '—',
            'asset'  => $this->asset?->name ?? '—',
            default  => $this->ingredient?->name ?? '—',
        };
    }
}
