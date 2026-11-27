<?php

namespace App\Actions\Prescription;

use App\Models\Prescription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PurgePrescriptionImageAction
{
    public function execute(int $id, int $userId, CarbonInterface $cutoff): bool
    {
        return DB::transaction(function () use ($id, $userId, $cutoff): bool {
            // Reservation creation locks the same customer before looking up the prescription.
            User::query()->lockForUpdate()->findOrFail($userId, ['id']);
            $prescription = Prescription::query()->where('user_id', $userId)
                ->dueForImagePurge($cutoff)->lockForUpdate()->find($id);

            if ($prescription === null || $prescription->activeReservations()->exists()) {
                return false;
            }

            $disk = Storage::disk('prescriptions');
            $path = $prescription->image_path;
            // A missing file can be reconciled if a prior run removed it before the database committed.
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException('Could not purge prescription image '.$id.'.');
            }

            $now = now();
            $prescription->forceFill(['image_path' => null, 'image_deleted_at' => $now])->save();
            DB::table('audit_logs')->insert([
                'actor_id' => null,
                'action' => 'prescription.image_purged',
                'subject_type' => Prescription::class,
                'subject_id' => $id,
                'created_at' => $now,
            ]);

            return true;
        }, attempts: 3);
    }
}
