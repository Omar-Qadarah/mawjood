<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    protected $fillable = [
        'generic_name_en',
        'generic_name_ar',
        'name_en',
        'name_ar',
        'dosage_form',
        'route',
        'strength_text',
        'brand_name',
        'manufacturer_name',
        'pack_size',
        'pack_unit',
        'barcode',
        'jfda_registration_no',
        'registration_status',
        'registration_expiry',
        'official_price',
        'currency',
        'official_price_effective_date',
        'dispensing_class',
        'search_normalized',
        'catalog_status',
    ];

    protected function casts(): array
    {
        return [
            'pack_size' => 'decimal:3',
            'official_price' => 'decimal:3',
            'official_price_effective_date' => 'date',
            'registration_expiry' => 'date',
        ];
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(MedicineIngredient::class);
    }
}
