<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function scopeMatchingCatalogSearch(Builder $query, string $search): Builder
    {
        $pattern = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        return $query->where(function (Builder $query) use ($pattern): void {
            $query->whereRaw("search_normalized LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("name_en LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("generic_name_en LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("generic_name_ar LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("barcode LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("jfda_registration_no LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('ingredients.activeIngredient', function (Builder $ingredient) use ($pattern): void {
                    $ingredient->whereRaw("name_en LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern]);
                });
        });
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(MedicineIngredient::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
