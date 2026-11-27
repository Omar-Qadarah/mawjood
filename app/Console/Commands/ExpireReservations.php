<?php

namespace App\Console\Commands;

use App\Actions\Reservation\ExpireReservationAction;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Throwable;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Expire pending reservations past their response deadline and release their stock holds';

    public function handle(ExpireReservationAction $action): int
    {
        $cutoff = now();
        $expired = 0;
        $failed = 0;

        Reservation::query()->select(['id', 'user_id'])
            ->where('status', ReservationStatus::Pending->value)
            ->where('response_deadline', '<=', $cutoff)
            ->chunkById(100, function ($reservations) use ($action, $cutoff, &$expired, &$failed): void {
                foreach ($reservations as $reservation) {
                    try {
                        $expired += (int) $action->execute($reservation->id, $reservation->user_id, $cutoff);
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                        $this->error('Could not expire reservation '.$reservation->id.'.');
                    }
                }
            });

        $this->info("Expired $expired reservation(s).");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
