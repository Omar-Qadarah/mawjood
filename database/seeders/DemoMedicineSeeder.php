<?php

namespace Database\Seeders;

use App\Models\ActiveIngredient;
use App\Models\Medicine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoMedicineSeeder extends Seeder
{
    public function run(): void
    {
        $packages = require __DIR__.'/data/demo_medicines.php';

        DB::transaction(function () use ($packages): void {
            foreach ($packages as $package) {
                $details = $package['medicine'];
                $medicine = Medicine::firstOrCreate([
                    'name_en' => $details['name_en'],
                    'manufacturer_name' => 'Mawjood Demo',
                ], $details + [
                    'brand_name' => '',
                    'search_normalized' => mb_strtolower(implode(' ', [
                        $details['name_en'],
                        $details['name_ar'],
                        $details['generic_name_en'],
                        $details['generic_name_ar'],
                        $details['strength_text'],
                    ])),
                    'catalog_status' => 'active',
                ]);

                foreach ($package['ingredients'] as $component) {
                    $ingredient = ActiveIngredient::firstOrCreate(
                        ['name_en' => $component['name_en']],
                        ['name_ar' => $component['name_ar']],
                    );

                    $medicine->ingredients()->firstOrCreate(
                        ['active_ingredient_id' => $ingredient->id],
                        [
                            'strength_value' => $component['strength_value'],
                            'strength_unit' => $component['strength_unit'],
                            'per_value' => $component['per_value'],
                            'per_unit' => $component['per_unit'],
                        ],
                    );
                }
            }
        });
    }
}
