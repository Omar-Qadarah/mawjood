<?php

namespace App\Actions\Pharmacy;

use App\Enums\PharmacyStatus;
use App\Models\Pharmacy;

class SaveAdminPharmacyAction
{
    public function execute(Pharmacy $pharmacy, array $data): Pharmacy
    {
        if (array_key_exists('status', $data)) {
            $pharmacy->status = PharmacyStatus::from($data['status']);
            unset($data['status']);
        }

        $pharmacy->fill($data)->save();

        return $pharmacy->refresh();
    }
}
