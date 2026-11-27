<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineIngredient extends Model
{
    protected $fillable = [
        'medicine_id',
        'active_ingredient_id',
        'strength_value',
        'strength_unit',
        'per_value',
        'per_unit',
    ];

    protected function casts(): array
    {
        return [
            'strength_value' => 'decimal:4',
            'per_value' => 'decimal:4',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function activeIngredient(): BelongsTo
    {
        return $this->belongsTo(ActiveIngredient::class);
    }
}
