<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\ActiveIngredient;
use App\Models\Medicine;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMedicineWriteTest extends TestCase
{
    use RefreshDatabase;

    private const MEDICINES = '/api/v1/admin/medicines';

    private const INGREDIENTS = '/api/v1/admin/active-ingredients';

    public function test_admin_can_manage_packages_shared_ingredients_and_strengths(): void
    {
        $this->actingAs($this->admin(), 'web');
        $first = $this->postJson(self::INGREDIENTS, ['name_en' => 'Alpha ingredient', 'name_ar' => 'مكوّن ألفا'])
            ->assertCreated()->json('data.id');
        $second = $this->postJson(self::INGREDIENTS, ['name_en' => 'Beta ingredient'])
            ->assertCreated()->json('data.id');

        $this->getJson(self::INGREDIENTS.'?search=Beta')->assertOk()->assertJsonPath('data.0.id', $second);
        $this->patchJson(self::INGREDIENTS.'/'.$first, ['name_en' => 'Alpha updated', 'atc_code' => 'DEMO'])
            ->assertOk()->assertJsonPath('data.name_en', 'Alpha updated');
        $this->getJson(self::INGREDIENTS.'/'.$first)->assertOk()->assertJsonPath('data.atc_code', 'DEMO');

        $id = $this->postJson(self::MEDICINES, $this->package() + ['ingredients' => [
            $this->ingredientComponent($first, '25'), $this->ingredientComponent($second, '2.5'),
        ]])->assertCreated()->assertJsonCount(2, 'data.ingredients')
            ->assertJsonPath('data.strength_text', '25 mg per 5 mL / 2.5 mg per 5 mL')
            ->assertJsonPath('data.brand_name', '')
            ->assertJsonPath('data.currency', 'JOD')
            ->json('data.id');

        $this->deleteJson(self::INGREDIENTS.'/'.$first)->assertConflict();
        $this->patchJson(self::MEDICINES.'/'.$id, ['name_en' => 'Updated demo package', 'ingredients' => [
            $this->ingredientComponent($first, '30.125'),
        ]])->assertOk()->assertJsonCount(1, 'data.ingredients')
            ->assertJsonPath('data.ingredients.0.strength_value', '30.1250')
            ->assertJsonPath('data.strength_text', '30.125 mg per 5 mL');

        $this->assertDatabaseMissing('medicine_ingredients', ['medicine_id' => $id, 'active_ingredient_id' => $second]);
        $this->deleteJson(self::INGREDIENTS.'/'.$second)->assertNoContent();
        $this->deleteJson(self::MEDICINES.'/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('medicine_ingredients', ['medicine_id' => $id]);
        $this->assertDatabaseHas('active_ingredients', ['id' => $first]);
        $this->deleteJson(self::INGREDIENTS.'/'.$first)->assertNoContent();
    }

    public function test_localized_catalog_names_reject_the_wrong_script_on_create_and_update(): void
    {
        $this->actingAs($this->admin(), 'web');

        $wrongNames = ['name_en' => 'Vitamin فيتامين', 'name_ar' => 'فيتامين Vitamin'];
        $this->postJson(self::INGREDIENTS, $wrongNames)
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys($wrongNames));

        $ingredient = $this->postJson(self::INGREDIENTS, ['name_en' => 'Vitamin B12', 'name_ar' => 'فيتامين ١٢'])
            ->assertCreated()->json('data.id');
        $this->patchJson(self::INGREDIENTS.'/'.$ingredient, $wrongNames)
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys($wrongNames));

        $wrongMedicineNames = $wrongNames + ['generic_name_en' => 'فيتامين', 'generic_name_ar' => 'Vitamin'];
        $package = $this->package() + ['ingredients' => [$this->ingredientComponent($ingredient, '2')]];
        $this->postJson(self::MEDICINES, $wrongMedicineNames + $package)
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys($wrongMedicineNames));

        $medicine = $this->postJson(self::MEDICINES, [
            'name_en' => 'Vitamin B12 (Tablets)',
            'name_ar' => 'فيتامين ١٢ (أقراص)',
            'generic_name_en' => 'Vitamin B12',
            'generic_name_ar' => 'فيتامين ١٢',
        ] + $package)->assertCreated()->json('data.id');
        $this->patchJson(self::MEDICINES.'/'.$medicine, $wrongMedicineNames)
            ->assertUnprocessable()->assertJsonValidationErrors(array_keys($wrongMedicineNames));
    }

    public function test_invalid_compositions_are_rejected_without_writing_a_package(): void
    {
        $this->actingAs($this->admin(), 'web');
        $ingredient = ActiveIngredient::create(['name_en' => 'Example ingredient']);
        $incomplete = $this->ingredientComponent($ingredient->id, '2');
        unset($incomplete['per_unit']);
        $this->postJson(self::MEDICINES, $this->package() + ['ingredients' => [$incomplete]])
            ->assertUnprocessable()->assertJsonValidationErrors('ingredients.0.per_unit');
        $this->postJson(self::MEDICINES, $this->package() + ['ingredients' => [
            $this->ingredientComponent($ingredient->id, '2'), $this->ingredientComponent($ingredient->id, '3'),
        ]])->assertUnprocessable()->assertJsonValidationErrors('ingredients.1.active_ingredient_id');
        $this->assertDatabaseCount('medicines', 0);
        $this->assertDatabaseCount('medicine_ingredients', 0);
    }

    public function test_stocked_medicine_and_its_composition_are_preserved_on_delete(): void
    {
        $this->actingAs($this->admin(), 'web');
        $this->seed(DemoMedicineSeeder::class);
        $this->seed(DemoInventorySeeder::class);
        $medicine = Medicine::where('name_en', 'Demo Paracetamol Tablets')->sole();

        $this->deleteJson(self::MEDICINES.'/'.$medicine->id)->assertConflict();
        $this->assertDatabaseHas('medicines', ['id' => $medicine->id]);
        $this->assertDatabaseHas('medicine_ingredients', ['medicine_id' => $medicine->id]);
        $this->assertDatabaseHas('inventory_items', ['medicine_id' => $medicine->id]);
    }

    public function test_customers_cannot_write_to_the_catalog(): void
    {
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->actingAs($customer, 'web');
        $this->postJson(self::MEDICINES)->assertForbidden();
        $this->postJson(self::INGREDIENTS)->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
    }

    private function package(): array
    {
        return [
            'name_en' => 'Test medicine package',
            'generic_name_en' => 'Test generic',
            'manufacturer_name' => 'Test manufacturer',
            'dosage_form' => 'suspension',
            'route' => 'oral',
            'pack_size' => '100.000',
            'pack_unit' => 'mL',
            'dispensing_class' => 'otc',
            'catalog_status' => 'active',
        ];
    }

    private function ingredientComponent(int $id, string $strength): array
    {
        return [
            'active_ingredient_id' => $id,
            'strength_value' => $strength,
            'strength_unit' => 'mg',
            'per_value' => '5',
            'per_unit' => 'mL',
        ];
    }
}
