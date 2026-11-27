<?php

namespace App\Http\Controllers\Api\V1\Pharmacy;

use App\Actions\Pharmacy\RegisterPharmacyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pharmacy\StorePharmacyRequest;
use App\Http\Resources\Api\V1\Pharmacy\PharmacyResource;
use Illuminate\Http\JsonResponse;

class PharmacyController extends Controller
{
    public function store(
        StorePharmacyRequest $request,
        RegisterPharmacyAction $action
    ): JsonResponse {
        $pharmacy = $action->execute(
            $request->user(),
            $request->validated()
        );

        return PharmacyResource::make($pharmacy)
            ->response()
            ->setStatusCode(201);
    }
}
