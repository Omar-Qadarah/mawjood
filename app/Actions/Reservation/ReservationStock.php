<?php

namespace App\Actions\Reservation;

use App\Enums\BatchAllocationStatus;
use App\Enums\InventoryBatchStatus;
use App\Models\BatchAllocation;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class ReservationStock
{
    public function heldAllocations(Reservation $reservation): Collection
    {
        return $reservation->allocations()->where('batch_allocations.status', BatchAllocationStatus::Held->value)->get();
    }

    public function lockBatches(array $itemIds, ?array $batchIds = null): Collection
    {
        // Lock all listings before any batches, in the same order as reservation creation.
        $items = InventoryItem::query()->whereKey($itemIds)->orderBy('id')->lockForUpdate()->pluck('id');
        $batches = new Collection;
        foreach ($items as $itemId) {
            $query = InventoryBatch::query()->where('inventory_item_id', $itemId);
            if ($batchIds !== null) {
                $query->whereKey($batchIds);
            }
            $batches = $batches->merge($query->orderBy('id')->lockForUpdate()->get());
        }

        return $batches->keyBy('id');
    }

    public function releaseAll(Reservation $reservation, string $reason, CarbonInterface $now): void
    {
        $held = $this->heldAllocations($reservation);
        $batches = $this->lockBatches(
            $held->pluck('inventory_item_id')->unique()->all(),
            $held->pluck('batch_id')->unique()->all(),
        );

        $this->release($held, $batches, $reason, $now);
    }

    public function restoreEligibleHolds(Reservation $reservation, CarbonInterface $now): bool
    {
        $items = $reservation->items()->get();
        $batches = $this->lockBatches($items->pluck('inventory_item_id')->all());
        $date = $now->copy()->setTimezone('Asia/Amman')->toDateString();
        $eligible = $batches->filter(fn (InventoryBatch $batch) => $batch->status === InventoryBatchStatus::Active
            && $batch->expiry_date->toDateString() >= $date);
        $eligibleByItem = $eligible->sortBy([['expiry_date', 'asc'], ['id', 'asc']])->groupBy('inventory_item_id');

        $held = $this->heldAllocations($reservation);
        foreach ($held->groupBy('batch_id') as $batchId => $allocations) {
            $this->checkHeldQuantity($batches[$batchId], $allocations->sum('quantity'));
        }

        $invalid = $held->reject(fn (BatchAllocation $allocation) => $eligible->has($allocation->batch_id));
        $this->release($invalid, $batches, 'ineligible_batch', $now);
        $validByLine = $held->diff($invalid)->groupBy('reservation_item_id');

        $complete = $items->isNotEmpty();
        foreach ($items as $item) {
            $remaining = $item->quantity - ($validByLine->get($item->id)?->sum('quantity') ?? 0);
            foreach ($eligibleByItem->get($item->inventory_item_id, []) as $batch) {
                if ($remaining <= 0) {
                    break;
                }
                $quantity = min($remaining, $batch->quantity_on_hand - $batch->quantity_held);
                if ($quantity === 0) {
                    continue;
                }

                $batch->increment('quantity_held', $quantity);
                $item->allocations()->create([
                    'inventory_item_id' => $item->inventory_item_id,
                    'batch_id' => $batch->id,
                    'quantity' => $quantity,
                    'status' => BatchAllocationStatus::Held,
                ]);
                $remaining -= $quantity;
            }
            $complete = $complete && $remaining === 0;
        }

        return $complete;
    }

    private function release(Collection $held, Collection $batches, string $reason, CarbonInterface $now): void
    {
        foreach ($held->groupBy('batch_id') as $batchId => $allocations) {
            $batch = $batches[$batchId];
            $quantity = $allocations->sum('quantity');
            $this->checkHeldQuantity($batch, $quantity);
            $batch->decrement('quantity_held', $quantity);
        }

        BatchAllocation::query()->whereKey($held->modelKeys())->update([
            'status' => BatchAllocationStatus::Released->value,
            'released_at' => $now,
            'release_reason' => $reason,
        ]);
    }

    private function checkHeldQuantity(InventoryBatch $batch, int $quantity): void
    {
        if ($batch->quantity_held < $quantity) {
            throw new RuntimeException('Held stock does not match allocations for batch '.$batch->id.'.');
        }
    }
}
