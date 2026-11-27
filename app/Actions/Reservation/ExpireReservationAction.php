<?php

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ExpireReservationAction
{
    public function __construct(private ReservationStock $stock) {}

    public function execute(int $reservationId, int $userId, CarbonInterface $cutoff): bool
    {
        return DB::transaction(function () use ($reservationId, $userId, $cutoff): bool {
            // Use the same customer-first order as reservation creation and review.
            User::query()->lockForUpdate()->findOrFail($userId, ['id']);
            $reservation = Reservation::query()->where('user_id', $userId)
                ->dueForExpiry($cutoff)->lockForUpdate()->find($reservationId);

            if ($reservation === null) {
                return false;
            }

            $previousStatus = $reservation->status;
            $reason = $previousStatus === ReservationStatus::Pending ? 'response_deadline' : 'pickup_deadline';
            $now = now();
            $this->stock->releaseAll($reservation, $reason, $now);
            $reservation->forceFill([
                'status' => ReservationStatus::Expired,
                'status_changed_at' => $now,
                'attention_reason' => null,
            ])->save();

            DB::table('audit_logs')->insert([
                'actor_id' => null,
                'action' => 'reservation.expired',
                'subject_type' => Reservation::class,
                'subject_id' => $reservation->id,
                'old_values' => json_encode(['status' => $previousStatus->value], JSON_THROW_ON_ERROR),
                'new_values' => json_encode([
                    'status' => ReservationStatus::Expired->value,
                    'reason' => $reason,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return true;
        }, attempts: 3);
    }
}
