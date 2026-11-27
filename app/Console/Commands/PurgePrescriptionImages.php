<?php

namespace App\Console\Commands;

use App\Actions\Prescription\PurgePrescriptionImageAction;
use App\Models\Prescription;
use Illuminate\Console\Command;
use Throwable;

class PurgePrescriptionImages extends Command
{
    protected $signature = 'prescriptions:purge-images';

    protected $description = 'Purge due prescription images with no active reservations';

    public function handle(PurgePrescriptionImageAction $purge): int
    {
        if (! config('prescriptions.purge_enabled')) {
            $this->info('Prescription image purging is disabled.');

            return self::SUCCESS;
        }

        $cutoff = now();
        $purged = 0;
        $failed = 0;
        Prescription::query()->select(['id', 'user_id'])->dueForImagePurge($cutoff)
            ->whereDoesntHave('activeReservations')
            ->chunkById(100, function ($prescriptions) use ($purge, $cutoff, &$purged, &$failed): void {
                foreach ($prescriptions as $prescription) {
                    try {
                        $purged += (int) $purge->execute($prescription->id, $prescription->user_id, $cutoff);
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                        $this->error('Could not purge prescription image '.$prescription->id.'.');
                    }
                }
            });

        $this->info("Purged $purged prescription image(s).");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
