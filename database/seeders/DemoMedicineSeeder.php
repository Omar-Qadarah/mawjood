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
        DB::transaction(function (): void {
            $medicine = Medicine::firstOrCreate([
                'name_en' => 'Mawjood Demo Package',
                'manufacturer_name' => 'Mawjood Demo',
            ], [
                'generic_name_en' => 'Paracetamol and caffeine',
                'generic_name_ar' => 'باراسيتامول وكافيين',
                'name_ar' => 'عبوة موجود التجريبية',
                'dosage_form' => 'tablet',
                'route' => 'oral',
                'strength_text' => '500 mg / 65 mg per tablet',
                'brand_name' => '',
                'pack_size' => '12.000',
                'pack_unit' => 'tablet',
                'dispensing_class' => 'otc',
                'search_normalized' => 'mawjood demo package paracetamol caffeine باراسيتامول كافيين',
                'catalog_status' => 'active',
            ]);

            $ingredients = [
                ['name_en' => 'Paracetamol', 'name_ar' => 'باراسيتامول', 'strength' => '500.0000'],
                ['name_en' => 'Caffeine', 'name_ar' => 'كافيين', 'strength' => '65.0000'],
            ];

            foreach ($ingredients as $data) {
                $ingredient = ActiveIngredient::firstOrCreate(
                    ['name_en' => $data['name_en']],
                    ['name_ar' => $data['name_ar']],
                );

                $medicine->ingredients()->firstOrCreate(
                    ['active_ingredient_id' => $ingredient->id],
                    [
                        'strength_value' => $data['strength'],
                        'strength_unit' => 'mg',
                        'per_value' => '1.0000',
                        'per_unit' => 'tablet',
                    ],
                );
            }
        });
    }
}
