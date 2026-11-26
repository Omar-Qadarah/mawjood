<?php

namespace App\Http\Controllers\Api\V1\Pharmacy;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pharmacy\StorePharmacyRequest;
use App\Http\Resources\Api\V1\Pharmacy\PharmacyResource;
use App\Models\Membership;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PharmacyController extends Controller
{
    public function store(StorePharmacyRequest $request): JsonResponse
    {
        $pharmacy = DB::transaction(function () use ($request) {

            // Create the pharmacy. Its status defaults to pending.
            $pharmacy = Pharmacy::create($request->validated());

            // Assign the authenticated user as its owner.
            $membership = new Membership([
                'user_id' => $request->user()->id,
                'pharmacy_id' => $pharmacy->id,
            ]);

            // Role is assigned explicitly because it is not fillable.
            $membership->role = MembershipRole::Owner;

            $membership->save();

            return $pharmacy;
        });

        return (new PharmacyResource($pharmacy))
            ->response()
            ->setStatusCode(201);
    }
}