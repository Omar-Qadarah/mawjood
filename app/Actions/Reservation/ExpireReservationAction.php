<?php

namespace App\Actions\Reservation;

use App\Enums\BatchAllocationStatus;
use App\Enums\ReservationStatus;
use App\Models\BatchAllocation;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpireReservationAction
{
    public function execute(int $reservationId, int $userId, CarbonInterface $cutoff): bool
    {
        return DB::transaction(function () use ($reservationId, $userId, $cutoff): bool {
            // Use the same customer-first order as reservation creation.
            User::query()->lockForUpdate()->findOrFail($userId, ['id']);
            $reservation = Reservation::query()->where('user_id', $userId)
                ->where('status', ReservationStatus::Pending->value)
                ->where('response_deadline', '<=', $cutoff)
                ->lockForUpdate()->find($reservationId);

            if ($reservation === null) {
                return false;
            }

            $held = $reservation->allocations()->where('batch_allocations.status', BatchAllocationStatus::Held->value)->get();
            $byBatch = $held->groupBy('batch_id');

            // Lock every listing before locking batches, in the same order as creation.
            $itemIds = InventoryItem::query()->whereKey($held->pluck('inventory_item_id')->unique()->all())
                ->orderBy('id')->lockForUpdate()->pluck('id');
            foreach ($itemIds as $itemId) {
                $batchIds = $held->where('inventory_item_id', $itemId)->pluck('batch_id')->unique()->all();
                $batches = InventoryBatch::query()->where('inventory_item_id', $itemId)->whereKey($batchIds)
                    ->orderBy('id')->lockForUpdate()->get();
                foreach ($batches as $batch) {
                    $quantity = $byBatch[$batch->id]->sum('quantity');
                    if ($batch->quantity_held < $quantity) {
                        throw new RuntimeException('Held stock does not match allocations for batch '.$batch->id.'.');
                    }
                    $batch->decrement('quantity_held', $quantity);
                }
            }

            $now = now();
            BatchAllocation::query()->whereKey($held->modelKeys())->update([
                'status' => BatchAllocationStatus::Released->value,
                'released_at' => $now,
                'release_reason' => 'response_deadline',
            ]);
            $reservation->update(['status' => ReservationStatus::Expired, 'status_changed_at' => $now]);

            DB::table('audit_logs')->insert([
                'actor_id' => null,
                'action' => 'reservation.expired',
                'subject_type' => Reservation::class,
                'subject_id' => $reservation->id,
                'old_values' => json_encode(['status' => ReservationStatus::Pending->value], JSON_THROW_ON_ERROR),
                'new_values' => json_encode([
                    'status' => ReservationStatus::Expired->value,
                    'reason' => 'response_deadline',
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return true;
        }, attempts: 3);
    }
}
