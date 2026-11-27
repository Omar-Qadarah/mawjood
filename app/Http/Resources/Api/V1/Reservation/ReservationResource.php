<?php

namespace App\Http\Resources\Api\V1\Reservation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'pharmacy_id' => $this->pharmacy_id,
            'prescription_id' => $this->prescription_id,
            'status' => $this->status->value,
            'status_changed_at' => $this->status_changed_at,
            'response_deadline' => $this->response_deadline,
            'customer_note' => $this->customer_note,
            'items' => $this->items->map(fn ($line) => [
                'inventory_item_id' => $line->inventory_item_id,
                'medicine_name_snapshot' => $line->medicine_name_snapshot,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
            ]),
        ];
    }
}
