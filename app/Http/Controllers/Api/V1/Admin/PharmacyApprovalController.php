<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PharmacyStatus;
use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\Response;

class PharmacyApprovalController extends Controller
{
    public function __invoke(Pharmacy $pharmacy): Response
    {
        $approved = Pharmacy::query()->whereKey($pharmacy->id)
            ->where('status', PharmacyStatus::Pending->value)
            ->update(['status' => PharmacyStatus::Active->value]);

        abort_if($approved === 0, 409, 'This pharmacy is no longer pending. Reopen its details to see its current status.');

        return response()->noContent();
    }
}
