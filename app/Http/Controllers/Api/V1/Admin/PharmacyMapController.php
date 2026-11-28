<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PharmacyMapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $completed = DB::table('reservations')->where('status', ReservationStatus::Completed->value);
        $counts = (clone $completed)->selectRaw('pharmacy_id, COUNT(*) AS total')
            ->groupBy('pharmacy_id')->pluck('total', 'pharmacy_id');

        $sites = Pharmacy::query()->orderBy('name_en')
            ->get(['id', 'name_en', 'city', 'governorate', 'latitude', 'longitude', 'status']);

        return response()->json([
            'data' => $sites->map(fn (Pharmacy $site) => [
                ...$site->toArray(),
                'completed_reservations' => (int) $counts->get($site->id, 0),
            ]),
        ]);
    }
}
