<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Pharmacy\SaveAdminPharmacyAction;
use App\Enums\PharmacyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StorePharmacyRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePharmacyRequest;
use App\Http\Resources\Api\V1\Admin\AdminPharmacyResource;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminPharmacyController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'governorate' => ['sometimes', Rule::in(array_keys(config('jordan')))],
            'city' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(PharmacyStatus::class)],
        ]);

        $pharmacies = Pharmacy::query()
            ->withCount('memberships')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name_ar', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('license_no', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('governorate', 'like', "%{$search}%");
                });
            })
            ->when($filters['governorate'] ?? null, fn ($query, string $governorate) => $query->where('governorate', $governorate))
            ->when($filters['city'] ?? null, fn($query, string $city) => $query->where('city', $city))
            ->when($filters['status'] ?? null, fn($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return AdminPharmacyResource::collection($pharmacies);
    }

    public function store(StorePharmacyRequest $request, SaveAdminPharmacyAction $action): JsonResponse
    {
        $pharmacy = $action->execute(new Pharmacy(), $request->validated())->loadCount('memberships');

        return AdminPharmacyResource::make($pharmacy)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Pharmacy $pharmacy): AdminPharmacyResource
    {
        return AdminPharmacyResource::make($pharmacy->loadCount('memberships')->load('memberships.user'));
    }

    public function update(
        UpdatePharmacyRequest $request,
        Pharmacy $pharmacy,
        SaveAdminPharmacyAction $action
    ): AdminPharmacyResource {
        return AdminPharmacyResource::make(
            $action->execute($pharmacy, $request->validated())->loadCount('memberships')
        );
    }

    public function destroy(Pharmacy $pharmacy): JsonResponse
    {
        DB::transaction(function () use ($pharmacy): void {
            $pharmacy->memberships()->delete();
            $pharmacy->delete();
        });

        return response()->json([
            'message' => 'Pharmacy and its memberships were deleted.',
        ]);
    }
}
