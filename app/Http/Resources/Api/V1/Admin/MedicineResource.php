<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'generic_name_en' => $this->generic_name_en,
            'generic_name_ar' => $this->generic_name_ar,
            'dosage_form' => $this->dosage_form,
            'route' => $this->route,
            'strength_text' => $this->strength_text,
            'brand_name' => $this->brand_name,
            'manufacturer_name' => $this->manufacturer_name,
            'pack_size' => $this->pack_size,
            'pack_unit' => $this->pack_unit,
            'catalog_status' => $this->catalog_status,
            $this->mergeWhen($this->relationLoaded('ingredients'), fn () => [
                'barcode' => $this->barcode,
                'jfda_registration_no' => $this->jfda_registration_no,
                'registration_status' => $this->registration_status,
                'registration_expiry' => $this->registration_expiry?->toDateString(),
                'official_price' => $this->official_price,
                'currency' => $this->currency,
                'official_price_effective_date' => $this->official_price_effective_date?->toDateString(),
                'dispensing_class' => $this->dispensing_class,
                'ingredients' => $this->ingredients->map(fn ($component) => [
                    'id' => $component->id,
                    'active_ingredient_id' => $component->active_ingredient_id,
                    'name_en' => $component->activeIngredient->name_en,
                    'name_ar' => $component->activeIngredient->name_ar,
                    'atc_code' => $component->activeIngredient->atc_code,
                    'strength_value' => $component->strength_value,
                    'strength_unit' => $component->strength_unit,
                    'per_value' => $component->per_value,
                    'per_unit' => $component->per_unit,
                ]),
            ]),
        ];
    }
}
