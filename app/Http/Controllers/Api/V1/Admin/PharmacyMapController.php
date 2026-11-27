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

        $sales = $completed
            ->join('reservation_items', 'reservation_items.reservation_id', '=', 'reservations.id')
            ->join('inventory_items', 'inventory_items.id', '=', 'reservation_items.inventory_item_id')
            ->join('medicines', 'medicines.id', '=', 'inventory_items.medicine_id')
            ->selectRaw('reservations.pharmacy_id, medicines.currency, SUM(reservation_items.quantity * reservation_items.unit_price) AS total')
            ->groupBy('reservations.pharmacy_id', 'medicines.currency')
            ->get()->groupBy('pharmacy_id');

        $sites = Pharmacy::query()->orderBy('name_en')
            ->get(['id', 'name_en', 'city', 'governorate', 'latitude', 'longitude', 'status']);

        return response()->json([
            'data' => $sites->map(fn (Pharmacy $site) => [
                ...$site->toArray(),
                'completed_reservations' => (int) $counts->get($site->id, 0),
                'sales_by_currency' => $sales->get($site->id, collect())->map(fn ($sale) => [
                    'currency' => $sale->currency,
                    'amount' => $sale->total,
                ])->values(),
            ]),
        ]);
    }
}
