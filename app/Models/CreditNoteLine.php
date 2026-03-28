<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteLine extends Model
{
    protected $fillable = [
        'credit_note_id', 'ingredient_id', 'description',
        'quantity', 'uom_id', 'unit_price', 'total_price', 'reason_code',
    ];

    protected $casts = [
        'quantity'    => 'decimal:4',
        'unit_price'  => 'decimal:4',
        'total_price' => 'decimal:4',
    ];

    public function creditNote(): BelongsTo { return $this->belongsTo(CreditNote::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
    public function uom(): BelongsTo { return $this->belongsTo(UnitOfMeasure::class, 'uom_id'); }
}
