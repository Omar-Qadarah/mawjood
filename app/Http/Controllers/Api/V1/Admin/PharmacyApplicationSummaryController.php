<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Pharmacy\SummarizePharmacyApplicationAction;
use App\Enums\PharmacyStatus;
use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;

class PharmacyApplicationSummaryController extends Controller
{
    public function __invoke(Pharmacy $pharmacy, SummarizePharmacyApplicationAction $summarize): JsonResponse
    {
        abort_unless($pharmacy->status === PharmacyStatus::Pending, 409, 'Review summaries are available for pending pharmacies.');

        return response()->json(['data' => $summarize->execute($pharmacy)])->header('Cache-Control', 'no-store');
    }
}
