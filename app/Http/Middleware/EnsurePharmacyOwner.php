<?php

namespace App\Http\Middleware;

use App\Enums\MembershipRole;
use App\Enums\PlatformRole;
use App\Models\InventoryItem;
use App\Models\Reservation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePharmacyOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->is_active && $user->platform_role === PlatformRole::Customer, 403);

        $item = $request->route('inventoryItem');
        $reservation = $request->route('reservation');
        $pharmacyId = match (true) {
            $item instanceof InventoryItem => $item->pharmacy_id,
            $reservation instanceof Reservation => $reservation->pharmacy_id,
            $item !== null || $reservation !== null => null,
            default => $request->input('pharmacy_id'),
        };

        abort_unless(filter_var($pharmacyId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) && $user->memberships()
            ->where('pharmacy_id', $pharmacyId)->where('role', MembershipRole::Owner->value)
            ->where('is_active', true)->exists(), 403);

        return $next($request);
    }
}
