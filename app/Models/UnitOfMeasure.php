<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    use HasFactory;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'name', 'abbreviation', 'type', 'is_base_unit', 'base_unit_factor', 'is_system',
    ];

    protected $casts = [
        'is_base_unit' => 'boolean',
        'is_system' => 'boolean',
        'base_unit_factor' => 'decimal:6',
    ];

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'base_uom_id');
    }

    public function recipeLinesAsUom(): HasMany
    {
        return $this->hasMany(RecipeLine::class, 'uom_id');
    }
}
