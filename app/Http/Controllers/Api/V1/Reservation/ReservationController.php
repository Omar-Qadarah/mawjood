<?php

namespace App\Http\Controllers\Api\V1\Reservation;

use App\Actions\Reservation\CreateReservationAction;
use App\Actions\Reservation\IssuePickupCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reservation\StoreReservationRequest;
use App\Http\Resources\Api\V1\Reservation\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function pickupCode(Request $request, Reservation $reservation, IssuePickupCodeAction $action): JsonResponse
    {
        $code = $action->execute($reservation, $request->user());

        return response()->json(['data' => ['pickup_code' => $code]], 201, ['Cache-Control' => 'no-store']);
    }

    public function store(StoreReservationRequest $request, CreateReservationAction $action): JsonResponse
    {
        $reservation = $action->execute($request->user(), $request->validated());

        return ReservationResource::make($reservation)->response()->setStatusCode($reservation->wasRecentlyCreated ? 201 : 200);
    }
}
