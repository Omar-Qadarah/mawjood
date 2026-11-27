<?php

namespace App\Actions\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IssuePickupCodeAction
{
    public function execute(Reservation $reservation, User $customer): string
    {
        abort_unless($customer->is_active && (int) $reservation->user_id === (int) $customer->id, 404);

        return DB::transaction(function () use ($reservation, $customer): string {
            User::query()->lockForUpdate()->findOrFail($customer->id, ['id']);
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            abort_unless($reservation->status === ReservationStatus::Confirmed && $reservation->pickup_deadline?->isFuture(),
                409, 'A pickup code is only available during the confirmed pickup window.');
            abort_if($reservation->pickup_code_hash !== null, 409, 'The pickup code has already been shown.');

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $reservation->forceFill(['pickup_code_hash' => $reservation->hashPickupCode($code)])->save();

            return $code;
        }, attempts: 3);
    }
}
