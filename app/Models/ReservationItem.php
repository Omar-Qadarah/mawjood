<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationItem extends Model
{
    protected $fillable = ['pharmacy_id', 'inventory_item_id', 'medicine_name_snapshot', 'quantity', 'unit_price'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'decimal:3'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BatchAllocation::class);
    }
}
