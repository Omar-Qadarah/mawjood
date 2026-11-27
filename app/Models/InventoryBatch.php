<?php

namespace App\Models;

use App\Enums\InventoryBatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBatch extends Model
{
    protected $fillable = [
        'inventory_item_id', 'batch_no', 'expiry_date', 'quantity_on_hand', 'quantity_held', 'status',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity_on_hand' => 'integer',
            'quantity_held' => 'integer',
            'status' => InventoryBatchStatus::class,
        ];
    }

    public function scopeEligibleOn(Builder $query, string $date): Builder
    {
        return $query->where('status', InventoryBatchStatus::Active->value)
            ->where('expiry_date', '>=', $date);
    }

    public function availableQuantityOn(string $date): int
    {
        if ($this->status !== InventoryBatchStatus::Active || $this->expiry_date->toDateString() < $date) {
            return 0;
        }

        return $this->quantity_on_hand - $this->quantity_held;
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
