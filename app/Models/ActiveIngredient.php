<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActiveIngredient extends Model
{
    protected $fillable = ['name_en', 'name_ar', 'atc_code'];

    public function medicineIngredients(): HasMany
    {
        return $this->hasMany(MedicineIngredient::class);
    }
}
