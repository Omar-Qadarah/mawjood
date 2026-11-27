<?php

namespace Tests\Feature;

use App\Models\ActiveIngredient;
use App\Models\Medicine;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_seeds_twenty_packages_without_duplicates(): void
    {
        $this->seed(DemoMedicineSeeder::class);
        $this->seed(DemoMedicineSeeder::class);

        $this->assertDatabaseCount('medicines', 20);
        $this->assertDatabaseCount('active_ingredients', 19);
        $this->assertDatabaseCount('medicine_ingredients', 22);

        $medicines = Medicine::with('ingredients.activeIngredient')->get();

        foreach ($medicines as $medicine) {
            $this->assertSame('Mawjood Demo', $medicine->manufacturer_name);
            $this->assertNotEmpty($medicine->ingredients);
            $this->assertNull($medicine->jfda_registration_no);
            $this->assertNull($medicine->official_price);

            foreach ($medicine->ingredients as $component) {
                $this->assertNotNull($component->activeIngredient);
                $this->assertTrue($component->medicine->is($medicine));
                $this->assertGreaterThan(0, (float) $component->strength_value);
                $this->assertGreaterThan(0, (float) $component->per_value);
            }
        }

        $paracetamol = ActiveIngredient::where('name_en', 'Paracetamol')->sole();
        $this->assertSame(3, $paracetamol->medicineIngredients()->count());
    }

    public function test_seeded_packages_preserve_combination_strengths_and_liquid_concentration(): void
    {
        $this->seed(DemoMedicineSeeder::class);

        $original = Medicine::with('ingredients.activeIngredient')->where('name_en', 'Mawjood Demo Package')->sole();
        $this->assertSame('12.000', $original->pack_size);
        $this->assertSame('JOD', $original->currency);
        $this->assertEquals([
            'Paracetamol' => '500.0000',
            'Caffeine' => '65.0000',
        ], $original->ingredients->mapWithKeys(
            fn ($component) => [$component->activeIngredient->name_en => $component->strength_value]
        )->all());

        $combination = Medicine::with('ingredients.activeIngredient')
            ->where('name_en', 'Demo Amoxicillin and Clavulanate Tablets')->sole();
        $this->assertEquals([
            'Amoxicillin' => '875.0000',
            'Clavulanic acid' => '125.0000',
        ], $combination->ingredients->mapWithKeys(
            fn ($component) => [$component->activeIngredient->name_en => $component->strength_value]
        )->all());

        $suspension = Medicine::where('name_en', 'Demo Paracetamol Suspension')->sole();
        $component = $suspension->ingredients()->sole();
        $this->assertSame('120.0000', $component->strength_value);
        $this->assertSame('mg', $component->strength_unit);
        $this->assertSame('5.0000', $component->per_value);
        $this->assertSame('mL', $component->per_unit);
    }
}
