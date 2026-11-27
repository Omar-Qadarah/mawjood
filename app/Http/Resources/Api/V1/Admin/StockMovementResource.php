<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'batch_no' => $this->batch->batch_no,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'reference' => $this->reference,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
