<?php

namespace App\Http\Controllers\Api\V1\Prescription;

use App\Actions\Prescription\UploadPrescriptionAction;
use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Prescription\StorePrescriptionRequest;
use App\Models\Prescription;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrescriptionController extends Controller
{
    public function store(StorePrescriptionRequest $request, UploadPrescriptionAction $upload): JsonResponse
    {
        $prescription = $upload->execute($request->user(), $request->file('image'));

        return response()->json(['data' => [
            'id' => $prescription->id,
            'status' => $prescription->status,
            'delete_after' => $prescription->delete_after,
        ]], 201);
    }

    public function image(Request $request, Reservation $reservation): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->is_active && $user->memberships()
            ->where('pharmacy_id', $reservation->pharmacy_id)
            ->where('is_active', true)
            ->whereIn('role', [MembershipRole::Owner->value, MembershipRole::Staff->value])
            ->exists(), 404);

        $prescription = Prescription::query()->where('user_id', $reservation->user_id)
            ->whereNotNull('image_path')->whereNull('image_deleted_at')->findOrFail($reservation->prescription_id);
        $disk = Storage::disk('prescriptions');
        abort_unless($disk->exists($prescription->image_path), 404);
        $stream = $disk->readStream($prescription->image_path);
        abort_unless(is_resource($stream), 404);

        $type = match (pathinfo($prescription->image_path, PATHINFO_EXTENSION)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        DB::table('audit_logs')->insert([
            'actor_id' => $user->id,
            'action' => 'prescription.image_viewed',
            'subject_type' => Prescription::class,
            'subject_id' => $prescription->id,
            'new_values' => json_encode(['reservation_id' => $reservation->id, 'pharmacy_id' => $reservation->pharmacy_id], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
