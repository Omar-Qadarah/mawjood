<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pharmacy' => [
                'id' => $this->pharmacy->id,
                'name_en' => $this->pharmacy->name_en,
                'name_ar' => $this->pharmacy->name_ar,
                'city' => $this->pharmacy->city,
                'status' => $this->pharmacy->status->value,
            ],
            'medicine' => [
                'id' => $this->medicine->id,
                'name_en' => $this->medicine->name_en,
                'name_ar' => $this->medicine->name_ar,
                'strength_text' => $this->medicine->strength_text,
                'pack_size' => $this->medicine->pack_size,
                'pack_unit' => $this->medicine->pack_unit,
                'currency' => $this->medicine->currency,
            ],
            'selling_price' => $this->selling_price,
            'is_listed' => $this->is_listed,
            'stock_verified_at' => $this->stock_verified_at,
            'available_quantity' => (int) $this->available_quantity,
            'batches_count' => $this->batches_count,
            'batches' => $this->whenLoaded('batches', fn () => $this->batches->map(fn ($batch) => [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'quantity_on_hand' => $batch->quantity_on_hand,
                'quantity_held' => $batch->quantity_held,
                'available_quantity' => $batch->available_quantity,
                'status' => $batch->status->value,
            ])),
        ];
    }
}
