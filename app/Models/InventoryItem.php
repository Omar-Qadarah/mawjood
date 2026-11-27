<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

class InventoryItem extends Model
{
    protected $fillable = ['pharmacy_id', 'medicine_id', 'selling_price', 'is_listed', 'stock_verified_at'];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:3',
            'is_listed' => 'boolean',
            'stock_verified_at' => 'datetime',
        ];
    }

    public function scopePublicMedicineSearch(Builder $query, string $term, string $date): Builder
    {
        return $query
            ->with(['medicine', 'pharmacy'])
            ->where('is_listed', true)
            ->whereHas('pharmacy', fn (Builder $query) => $query->searchable())
            ->whereHas('medicine', fn (Builder $query) => $query->matchingCatalogSearch($term))
            ->whereHas('batches', fn (Builder $query) => $query->eligibleOn($date)
                ->whereColumn('quantity_on_hand', '>', 'quantity_held'))
            ->withAvailableQuantity($date)
            ->orderBy('id');
    }

    public function scopeWithAvailableQuantity(Builder $query, string $date): Builder
    {
        return $query->withSum(
            ['batches as available_quantity' => fn (Builder $batch) => $batch->eligibleOn($date)],
            DB::raw('quantity_on_hand - quantity_held'),
        );
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function movements(): HasManyThrough
    {
        return $this->hasManyThrough(InventoryMovement::class, InventoryBatch::class, 'inventory_item_id', 'batch_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }
}
