<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletPrepRequestLine extends Model
{
    protected $fillable = [
        'outlet_prep_request_id', 'recipe_id', 'ingredient_id',
        'requested_quantity', 'fulfilled_quantity', 'uom_id', 'notes',
    ];

    protected $casts = [
        'requested_quantity' => 'decimal:4',
        'fulfilled_quantity' => 'decimal:4',
    ];

    public function prepRequest(): BelongsTo { return $this->belongsTo(OutletPrepRequest::class, 'outlet_prep_request_id'); }
    public function recipe(): BelongsTo { return $this->belongsTo(Recipe::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }
}
