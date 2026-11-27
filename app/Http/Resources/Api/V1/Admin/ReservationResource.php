<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'pharmacy' => [
                'id' => $this->pharmacy->id,
                'name_en' => $this->pharmacy->name_en,
            ],
            'status' => $this->status->value,
            'status_changed_at' => $this->status_changed_at,
            'response_deadline' => $this->response_deadline,
            'pickup_deadline' => $this->pickup_deadline,
            'held_quantity' => (int) $this->held_quantity,
            'rejection_reason' => $this->whenLoaded('items', fn () => $this->rejection_reason),
            'attention_reason' => $this->whenLoaded('items', fn () => $this->attention_reason),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'medicine_name_snapshot' => $item->medicine_name_snapshot,
                'quantity' => $item->quantity,
                'allocations' => $item->allocations->map(fn ($allocation) => [
                    'id' => $allocation->id,
                    'batch_no' => $allocation->batch->batch_no,
                    'quantity' => $allocation->quantity,
                    'status' => $allocation->status->value,
                ]),
            ])),
        ];
    }
}
