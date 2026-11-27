<?php

namespace App\Http\Controllers\Api\V1\Reservation;

use App\Actions\Reservation\CreateReservationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reservation\StoreReservationRequest;
use App\Http\Resources\Api\V1\Reservation\ReservationResource;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    public function store(StoreReservationRequest $request, CreateReservationAction $action): JsonResponse
    {
        $reservation = $action->execute($request->user(), $request->validated());

        return ReservationResource::make($reservation)->response()->setStatusCode($reservation->wasRecentlyCreated ? 201 : 200);
    }
}
