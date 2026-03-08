<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormTemplateLine extends Model
{
    protected $fillable = [
        'form_template_id', 'item_type', 'ingredient_id', 'recipe_id',
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

    public function itemName(): string
    {
        if ($this->item_type === 'recipe') {
            return $this->recipe?->name ?? '—';
        }
        return $this->ingredient?->name ?? '—';
    }
}
