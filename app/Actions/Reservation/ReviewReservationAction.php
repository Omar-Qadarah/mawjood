<?php

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewReservationAction
{
    public function __construct(private ReservationStock $stock) {}

    public function execute(Reservation $reservation, User $admin, ReservationStatus $decision, ?string $reason): Reservation
    {
        return DB::transaction(function () use ($reservation, $admin, $decision, $reason): Reservation {
            // Serialize transitions for the same customer with creation and expiry.
            User::query()->lockForUpdate()->findOrFail($reservation->user_id, ['id']);
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            $now = now();
            abort_unless($reservation->status === ReservationStatus::Pending, 409, 'Only pending reservations can be reviewed.');
            abort_if($reservation->response_deadline->lessThanOrEqualTo($now), 409, 'The response deadline has passed.');

            if ($decision === ReservationStatus::Confirmed && ! $this->stock->restoreEligibleHolds($reservation, $now)) {
                $reservation->forceFill(['attention_reason' => 'insufficient_eligible_stock'])->save();

                return $reservation;
            }

            $changes = ['status' => $decision, 'status_changed_at' => $now, 'handled_by' => $admin->id, 'attention_reason' => null];
            $audit = ['status' => $decision->value];
            if ($decision === ReservationStatus::Confirmed) {
                $pickupDeadline = $now->copy()->addMinutes(config('reservations.pickup_window_minutes'));
                $changes['pickup_deadline'] = $pickupDeadline;
                $audit['pickup_deadline'] = $pickupDeadline->toISOString();
            } else {
                $changes['rejection_reason'] = $reason;
                $audit['rejection_reason'] = $reason;
                $this->stock->releaseAll($reservation, 'rejected', $now);
            }

            $reservation->forceFill($changes)->save();
            DB::table('audit_logs')->insert([
                'actor_id' => $admin->id,
                'action' => 'reservation.'.$decision->value,
                'subject_type' => Reservation::class,
                'subject_id' => $reservation->id,
                'old_values' => json_encode(['status' => ReservationStatus::Pending->value], JSON_THROW_ON_ERROR),
                'new_values' => json_encode($audit, JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return $reservation;
        }, attempts: 3);
    }
}
