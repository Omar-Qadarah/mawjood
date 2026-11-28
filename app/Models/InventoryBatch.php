<?php

namespace App\Models;

use App\Enums\InventoryBatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function scopeExpiringSoon(Builder $query): Builder
    {
        $today = today('Asia/Amman');

        return $query->where('quantity_on_hand', '>', 0)->whereBetween('expiry_date', [
            $today->toDateString(),
            $today->copy()->addDays(30)->toDateString(),
        ]);
    }

    public function availableQuantityOn(string $date): int
    {
        if ($this->status !== InventoryBatchStatus::Active || $this->expiry_date->toDateString() < $date) {
            return 0;
        }

        return $this->quantity_on_hand - $this->quantity_held;
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'batch_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
