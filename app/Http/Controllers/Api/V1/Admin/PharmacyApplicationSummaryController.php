<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Pharmacy\GeneratePharmacyReviewSummaryAction;
use App\Actions\Pharmacy\SummarizePharmacyApplicationAction;
use App\Enums\PharmacyStatus;
use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyApplicationSummaryController extends Controller
{
    public function __invoke(
        Request $request,
        Pharmacy $pharmacy,
        SummarizePharmacyApplicationAction $summarize,
        GeneratePharmacyReviewSummaryAction $generate,
    ): JsonResponse {
        abort_unless($pharmacy->status === PharmacyStatus::Pending, 409, 'Review summaries are available for pending pharmacies.');

        $review = $summarize->execute($pharmacy);
        $aiSummary = $request->isMethod('POST') ? $generate->execute($review['signals']) : null;

        return response()->json(['data' => [
            'summary' => $aiSummary ?? $review['summary'],
            'matches' => $review['matches'],
            'source' => $aiSummary === null ? 'directory' : 'ai',
        ]])->header('Cache-Control', 'no-store');
    }
}
