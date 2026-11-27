<?php

namespace App\Actions\Reservation;

use App\Enums\BatchAllocationStatus;
use App\Enums\ReservationStatus;
use App\Models\BatchAllocation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompleteReservationAction
{
    public function __construct(private ReservationStock $stock) {}

    public function execute(Reservation $reservation, User $admin, string $code): Reservation
    {
        $result = DB::transaction(function () use ($reservation, $admin, $code): Reservation|string {
            User::query()->lockForUpdate()->findOrFail($reservation->user_id, ['id']);
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $now = now();
            abort_unless($reservation->status === ReservationStatus::Confirmed && $reservation->pickup_deadline?->greaterThan($now),
                409, 'Only a confirmed reservation within its pickup window can be completed.');
            abort_if($reservation->pickup_code_hash === null, 409, 'The customer must request their pickup code first.');
            abort_if($reservation->pickup_attempts >= 5, 429, 'The pickup code attempt limit has been reached.');
            if (! hash_equals($reservation->pickup_code_hash, $reservation->hashPickupCode($code))) {
                $reservation->increment('pickup_attempts');

                return 'The pickup code is incorrect.';
            }

            if (! $this->stock->restoreEligibleHolds($reservation, $now)) {
                $reservation->forceFill(['attention_reason' => 'insufficient_eligible_stock'])->save();

                return 'There is not enough eligible stock to complete this reservation.';
            }

            $held = $this->stock->heldAllocations($reservation);
            $batches = $this->stock->lockBatches(
                $held->pluck('inventory_item_id')->unique()->all(),
                $held->pluck('batch_id')->unique()->all(),
            );
            abort_unless($reservation->pickup_deadline->isFuture(), 409, 'The pickup deadline has passed.');
            foreach ($held->groupBy('batch_id') as $batchId => $allocations) {
                $batch = $batches[$batchId];
                $quantity = $allocations->sum('quantity');
                $batch->update([
                    'quantity_on_hand' => $batch->quantity_on_hand - $quantity,
                    'quantity_held' => $batch->quantity_held - $quantity,
                ]);
                $batch->movements()->create([
                    'type' => 'dispense',
                    'quantity' => -$quantity,
                    'reservation_id' => $reservation->id,
                    'created_by' => $admin->id,
                ]);
            }

            BatchAllocation::query()->whereKey($held->modelKeys())->update(['status' => BatchAllocationStatus::Fulfilled->value]);
            $reservation->forceFill([
                'status' => ReservationStatus::Completed,
                'status_changed_at' => now(),
                'handled_by' => $admin->id,
                'attention_reason' => null,
                'pickup_code_hash' => null,
            ])->save();

            DB::table('audit_logs')->insert([
                'actor_id' => $admin->id,
                'action' => 'reservation.completed',
                'subject_type' => Reservation::class,
                'subject_id' => $reservation->id,
                'old_values' => json_encode(['status' => ReservationStatus::Confirmed->value], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['status' => ReservationStatus::Completed->value], JSON_THROW_ON_ERROR),
                'created_at' => $reservation->status_changed_at,
            ]);

            return $reservation;
        }, attempts: 3);

        if (is_string($result)) {
            abort(409, $result);
        }

        return $result;
    }
}
