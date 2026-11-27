<?php

namespace App\Actions\Prescription;

use App\Models\Prescription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadPrescriptionAction
{
    public function execute(User $customer, UploadedFile $image): Prescription
    {
        $disk = Storage::disk('prescriptions');
        $path = $disk->putFile('', $image, 'private');

        try {
            return Prescription::create([
                'user_id' => $customer->id,
                'image_path' => $path,
                'status' => 'uploaded',
                'delete_after' => now()->addDays(config('prescriptions.retention_days')),
            ]);
        } catch (Throwable $exception) {
            try {
                $disk->delete($path);
            } catch (Throwable $cleanupFailure) {
                report($cleanupFailure);
            }

            throw $exception;
        }
    }
}
