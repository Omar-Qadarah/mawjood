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

    public function test_demo_package_loads_both_ingredient_relationships_without_duplicate_seeding(): void
    {
        $this->seed(DemoMedicineSeeder::class);
        $this->seed(DemoMedicineSeeder::class);

        $medicine = Medicine::with('ingredients.activeIngredient')
            ->where('name_en', 'Mawjood Demo Package')
            ->sole();

        $strengths = $medicine->ingredients
            ->mapWithKeys(fn ($component) => [$component->activeIngredient->name_en => $component->strength_value]);

        $this->assertCount(2, $strengths);
        $this->assertSame('500.0000', $strengths['Paracetamol']);
        $this->assertSame('65.0000', $strengths['Caffeine']);
        $this->assertSame('12.000', $medicine->pack_size);
        $this->assertSame('JOD', $medicine->currency);

        $caffeine = ActiveIngredient::where('name_en', 'Caffeine')->sole();
        $component = $caffeine->medicineIngredients()->sole();
        $this->assertTrue($component->medicine->is($medicine));
        $this->assertSame('mg', $component->strength_unit);
        $this->assertSame('1.0000', $component->per_value);
        $this->assertSame('tablet', $component->per_unit);

        $this->assertDatabaseCount('medicines', 1);
        $this->assertDatabaseCount('active_ingredients', 2);
        $this->assertDatabaseCount('medicine_ingredients', 2);
    }
}
