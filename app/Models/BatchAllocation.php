<?php

namespace App\Models;

use App\Enums\BatchAllocationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchAllocation extends Model
{
    protected $fillable = ['inventory_item_id', 'batch_id', 'quantity', 'status'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'status' => BatchAllocationStatus::class, 'released_at' => 'datetime'];
    }

    public function reservationItem(): BelongsTo
    {
        return $this->belongsTo(ReservationItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }
}
