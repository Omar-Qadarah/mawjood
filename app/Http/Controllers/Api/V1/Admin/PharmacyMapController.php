<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;

class PharmacyMapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => Pharmacy::query()->orderBy('name_en')
                ->get(['id', 'name_en', 'city', 'governorate', 'latitude', 'longitude', 'status']),
        ]);
    }
}
