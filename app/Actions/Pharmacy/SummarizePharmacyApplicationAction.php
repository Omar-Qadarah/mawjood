<?php

namespace App\Actions\Pharmacy;

use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Builder;

class SummarizePharmacyApplicationAction
{
    public function execute(Pharmacy $pharmacy): array
    {
        $matches = Pharmacy::query()->whereKeyNot($pharmacy->id)
            ->where(fn (Builder $query) => $query
                ->where('name_en', $pharmacy->name_en)->orWhere('name_ar', $pharmacy->name_ar)
                ->orWhere(fn (Builder $location) => $location->where('governorate', $pharmacy->governorate)
                    ->where('city', $pharmacy->city)->where('address', $pharmacy->address)))
            ->orderBy('id')->limit(5)->get(['id', 'name_en', 'name_ar', 'city', 'governorate', 'address'])
            ->map(fn (Pharmacy $match) => [
                'id' => $match->id,
                'name_en' => $match->name_en,
                'city' => $match->city,
                'same_name' => mb_strtolower($match->name_en) === mb_strtolower($pharmacy->name_en) || $match->name_ar === $pharmacy->name_ar,
                'same_address' => $match->governorate === $pharmacy->governorate && $match->city === $pharmacy->city
                    && mb_strtolower($match->address) === mb_strtolower($pharmacy->address),
            ])->values();

        $summary = [$matches->isEmpty()
            ? 'No matching name or address was found in the directory.'
            : 'Compare the listed records for possible overlap before approval.'];

        if (blank($pharmacy->phone) && blank($pharmacy->email)) {
            $summary[] = 'No contact details were provided.';
        }
        if (blank($pharmacy->opening_hours)) {
            $summary[] = 'No opening hours were provided.';
        }
        $summary[] = 'Verify the license and location before approving.';

        return ['summary' => implode(' ', $summary), 'matches' => $matches];
    }
}
