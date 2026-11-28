<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MembershipRole;
use App\Enums\PharmacyStatus;
use App\Enums\PlatformRole;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Membership;
use App\Models\Pharmacy;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardSummaryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $totals = Pharmacy::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $today = today();
        $firstDay = $today->copy()->subDays(29);
        $dailyRegistrations = Pharmacy::query()->where('created_at', '>=', $firstDay)
            ->where('created_at', '<', $today->copy()->addDay())
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')
            ->groupByRaw('DATE(created_at)')->pluck('total', 'day');

        return response()->json([
            'registrations' => array_map(function (int $offset) use ($firstDay, $dailyRegistrations): array {
                $day = $firstDay->copy()->addDays($offset)->toDateString();

                return ['date' => $day, 'total' => (int) $dailyRegistrations->get($day, 0)];
            }, range(0, 29)),
            'counts' => [
                'total' => (int) $totals->sum(),
                'pending' => (int) $totals->get(PharmacyStatus::Pending->value, 0),
                'active' => (int) $totals->get(PharmacyStatus::Active->value, 0),
                'suspended' => (int) $totals->get(PharmacyStatus::Suspended->value, 0),
            ],
            'customers_count' => User::where('platform_role', PlatformRole::Customer->value)->count(),
            'completed_reservations_count' => Reservation::where('status', ReservationStatus::Completed->value)->count(),
            'upcoming_reservations_count' => Reservation::query()->nearingDeadline()->count(),
            'low_stock_count' => InventoryItem::query()->lowStock(today('Asia/Amman')->toDateString())->count(),
            'expiring_batches_count' => InventoryBatch::query()->expiringSoon()->count(),
            'owners_count' => Membership::where('role', MembershipRole::Owner->value)
                ->where('is_active', true)->distinct()->count('user_id'),
            'recent_activity' => Pharmacy::query()
                ->whereColumn('updated_at', '>', 'created_at')
                ->latest('updated_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'name_en', 'status', 'updated_at']),
            'recent_pharmacies' => Pharmacy::query()
                ->latest()
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'name_en', 'status', 'created_at']),
        ]);
    }
}
