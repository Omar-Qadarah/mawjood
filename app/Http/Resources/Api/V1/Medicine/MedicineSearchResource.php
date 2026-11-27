<?php

namespace App\Http\Resources\Api\V1\Medicine;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicineSearchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medicine' => [
                'id' => $this->medicine->id,
                'name_en' => $this->medicine->name_en,
                'name_ar' => $this->medicine->name_ar,
                'strength_text' => $this->medicine->strength_text,
                'dosage_form' => $this->medicine->dosage_form,
                'pack_size' => $this->medicine->pack_size,
                'pack_unit' => $this->medicine->pack_unit,
            ],
            'pharmacy' => [
                'id' => $this->pharmacy->id,
                'name_en' => $this->pharmacy->name_en,
                'name_ar' => $this->pharmacy->name_ar,
                'city' => $this->pharmacy->city,
                'address' => $this->pharmacy->address,
            ],
            'selling_price' => $this->selling_price,
            'currency' => $this->medicine->currency,
            'available_quantity' => (int) $this->available_quantity,
        ];
    }
}
