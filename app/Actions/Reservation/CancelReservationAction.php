<?php

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    public function __construct(private ReservationStock $stock) {}

    public function execute(Reservation $reservation, User $admin): Reservation
    {
        return DB::transaction(function () use ($reservation, $admin): Reservation {
            User::query()->lockForUpdate()->findOrFail($reservation->user_id, ['id']);
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $previous = $reservation->status;
            abort_unless(in_array($previous, [ReservationStatus::Pending, ReservationStatus::Confirmed], true),
                409, 'Only pending or confirmed reservations can be cancelled.');

            $now = now();
            $this->stock->releaseAll($reservation, 'cancelled', $now);
            $reservation->forceFill([
                'status' => ReservationStatus::Cancelled,
                'status_changed_at' => $now,
                'handled_by' => $admin->id,
                'attention_reason' => null,
                'pickup_code_hash' => null,
            ])->save();

            DB::table('audit_logs')->insert([
                'actor_id' => $admin->id,
                'action' => 'reservation.cancelled',
                'subject_type' => Reservation::class,
                'subject_id' => $reservation->id,
                'old_values' => json_encode(['status' => $previous->value], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['status' => ReservationStatus::Cancelled->value], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return $reservation;
        }, attempts: 3);
    }
}
