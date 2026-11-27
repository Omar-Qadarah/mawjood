<?php

namespace App\Actions\Medicine;

use App\Models\Medicine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveMedicineAction
{
    public function execute(Medicine $medicine, array $data): Medicine
    {
        return DB::transaction(function () use ($medicine, $data): Medicine {
            if ($medicine->exists) {
                $medicine = Medicine::query()->lockForUpdate()->findOrFail($medicine->getKey());
            }

            $components = $data['ingredients'] ?? null;
            unset($data['ingredients']);

            if (array_key_exists('brand_name', $data)) {
                $data['brand_name'] ??= '';
            } elseif (! $medicine->exists) {
                $data['brand_name'] = '';
            }

            $medicine->fill($data);
            if ($components !== null) {
                $medicine->strength_text = $this->displayStrength($components);
            }

            $medicine->search_normalized = Str::limit(Str::lower(implode(' ', array_filter([
                $medicine->name_en,
                $medicine->name_ar,
                $medicine->generic_name_en,
                $medicine->generic_name_ar,
                $medicine->brand_name,
                $medicine->manufacturer_name,
                $medicine->strength_text,
            ]))), 500, '');

            $medicine->save();

            if ($components !== null) {
                $medicine->ingredients()->whereNotIn('active_ingredient_id', array_column($components, 'active_ingredient_id'))->delete();

                foreach ($components as $component) {
                    $medicine->ingredients()->updateOrCreate(
                        ['active_ingredient_id' => $component['active_ingredient_id']],
                        [
                            'strength_value' => $component['strength_value'],
                            'strength_unit' => $component['strength_unit'],
                            'per_value' => $component['per_value'] ?? null,
                            'per_unit' => $component['per_unit'] ?? null,
                        ],
                    );
                }
            }

            return $medicine->refresh();
        }, attempts: 3);
    }

    private function displayStrength(array $components): string
    {
        $display = array_map(function (array $component): string {
            $strength = $this->formatNumber($component['strength_value']).' '.$component['strength_unit'];

            if (isset($component['per_value'], $component['per_unit'])) {
                $per = $this->formatNumber($component['per_value']);
                $strength .= ' per '.($per === '1' ? '' : $per.' ').$component['per_unit'];
            }

            return $strength;
        }, $components);
        $strength = implode(' / ', $display);
        if (Str::length($strength) > 120) {
            throw ValidationException::withMessages(['ingredients' => 'Use shorter unit labels so the strength summary fits within 120 characters.']);
        }

        return $strength;
    }

    private function formatNumber(string|int|float $value): string
    {
        $number = number_format((float) $value, 4, '.', '');

        return rtrim(rtrim($number, '0'), '.');
    }
}
