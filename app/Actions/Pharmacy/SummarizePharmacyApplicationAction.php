<?php

namespace App\Actions\Pharmacy;

use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;

class SummarizePharmacyApplicationAction
{
    public function execute(Pharmacy $pharmacy): array
    {
        $sameName = '(name_en = ? OR name_ar = ?)';
        $sameAddress = '(governorate = ? AND city = ? AND address = ?)';
        $names = [$pharmacy->name_en, $pharmacy->name_ar];
        $address = [$pharmacy->governorate, $pharmacy->city, $pharmacy->address];

        $matches = Pharmacy::query()->whereKeyNot($pharmacy->id)
            ->whereRaw("($sameName OR $sameAddress)", [...$names, ...$address])
            ->select(['id', 'name_en', 'name_ar', 'city', 'governorate', 'address', 'license_no', 'status'])
            ->selectRaw("$sameName AS same_name", $names)
            ->selectRaw("$sameAddress AS same_address", $address);

        $counts = DB::query()->fromSub(clone $matches, 'matches')
            ->selectRaw('COUNT(*) AS match_count')
            ->selectRaw('COUNT(CASE WHEN same_name = 1 AND same_address = 0 THEN 1 END) AS name_only_count')
            ->selectRaw('COUNT(CASE WHEN same_name = 0 AND same_address = 1 THEN 1 END) AS address_only_count')
            ->selectRaw('COUNT(CASE WHEN same_name = 1 AND same_address = 1 THEN 1 END) AS name_and_address_count')
            ->first();

        $hasContact = filled($pharmacy->phone) || filled($pharmacy->email);
        $hasOpeningHours = filled($pharmacy->opening_hours);
        $summary = [(int) $counts->match_count === 0
            ? 'No matching name or address was found in the directory.'
            : 'Compare the listed records for possible overlap before approval.'];

        if (! $hasContact) {
            $summary[] = 'No contact details were provided.';
        }
        if (! $hasOpeningHours) {
            $summary[] = 'No opening hours were provided.';
        }
        $summary[] = 'Verify the license and location before approving.';

        return [
            'summary' => implode(' ', $summary),
            'matches' => $matches->selectRaw('(name_en = ?) AS same_english_name', [$pharmacy->name_en])
                ->selectRaw('(name_ar = ?) AS same_arabic_name', [$pharmacy->name_ar])
                ->orderBy('id')->limit(5)->get()->map(fn (Pharmacy $match) => [
                    'id' => $match->id,
                    'name_en' => $match->name_en,
                    'name_ar' => $match->name_ar,
                    'city' => $match->city,
                    'governorate' => $match->governorate,
                    'address' => $match->address,
                    'license_no' => $match->license_no,
                    'status' => $match->status->value,
                    'same_name' => (bool) $match->same_name,
                    'same_english_name' => (bool) $match->same_english_name,
                    'same_arabic_name' => (bool) $match->same_arabic_name,
                    'same_address' => (bool) $match->same_address,
                ])->values(),
            'signals' => [
                ...(array) $counts,
                'has_contact' => $hasContact,
                'has_opening_hours' => $hasOpeningHours,
            ],
        ];
    }
}
