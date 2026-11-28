<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpiringInventoryBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->inventoryItem;

        return [
            'id' => $this->id,
            'batch_no' => $this->batch_no,
            'expiry_date' => $this->expiry_date->toDateString(),
            'quantity_on_hand' => $this->quantity_on_hand,
            'status' => $this->status->value,
            'item' => [
                'id' => $item->id,
                'pharmacy' => ['name_en' => $item->pharmacy->name_en, 'city' => $item->pharmacy->city],
                'medicine' => ['name_en' => $item->medicine->name_en, 'strength_text' => $item->medicine->strength_text],
            ],
        ];
    }
}
