<?php

namespace App\Http\Controllers\Api\V1\Pharmacy;

use App\Actions\Pharmacy\RegisterPharmacyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharmacy\RegisterPharmacyRequest;
use App\Http\Resources\PharmacyResource;
use Illuminate\Http\JsonResponse;

class RegisterPharmacyController extends Controller
{
    public function __invoke(
        RegisterPharmacyRequest $request,
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