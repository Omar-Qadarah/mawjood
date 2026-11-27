<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MembershipRole;
use App\Enums\PharmacyStatus;
use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Pharmacy;
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

        return response()->json([
            'counts' => [
                'total' => (int) $totals->sum(),
                'pending' => (int) $totals->get(PharmacyStatus::Pending->value, 0),
                'active' => (int) $totals->get(PharmacyStatus::Active->value, 0),
                'suspended' => (int) $totals->get(PharmacyStatus::Suspended->value, 0),
            ],
            'customers_count' => User::where('platform_role', PlatformRole::Customer->value)->count(),
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
