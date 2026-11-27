<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\ActiveIngredient;
use App\Models\Medicine;
use App\Models\User;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMedicineCatalogTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/medicines';

    public function test_admin_can_list_and_paginate_medicine_packages(): void
    {
        $this->seed(DemoMedicineSeeder::class);
        $this->actingAs($this->admin(), 'web');

        $first = $this->getJson(self::ENDPOINT)->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonMissingPath('data.0.ingredients');
        $second = $this->getJson(self::ENDPOINT.'?page=2')->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2);

        $ids = array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'));
        $this->assertCount(20, array_unique($ids));
    }

    public function test_admin_can_search_by_english_or_arabic_ingredient_name_and_barcode(): void
    {
        $this->seed(DemoMedicineSeeder::class);
        ActiveIngredient::where('name_en', 'Caffeine')->sole()->update(['name_ar' => 'مكوّن اختباري']);
        Medicine::where('name_en', 'Mawjood Demo Package')->sole()->update(['barcode' => 'DEMO%_123']);
        $this->actingAs($this->admin(), 'web');

        foreach (['Caffeine', 'مكوّن اختباري', 'DEMO%_123'] as $search) {
            $this->getJson(self::ENDPOINT.'?'.http_build_query(['search' => $search]))->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.name_en', 'Mawjood Demo Package');
        }

        $this->getJson(self::ENDPOINT.'?search='.rawurlencode('%'))->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name_en', 'Mawjood Demo Package');
        Medicine::where('name_en', 'Mawjood Demo Package')->update(['catalog_status' => 'archived']);
        $this->getJson(self::ENDPOINT.'?search=Caffeine&catalog_status=archived')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.catalog_status', 'archived')
            ->assertJsonPath('meta.catalog_statuses', ['active', 'archived']);
        $this->getJson(self::ENDPOINT.'?search=Caffeine&catalog_status=active')->assertOk()->assertJsonPath('meta.total', 0);

        ActiveIngredient::create(['name_en' => 'Unused Demo Ingredient']);
        $ingredients = '/api/v1/admin/active-ingredients';
        $this->getJson($ingredients.'?search=Unused&usage=unused')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($ingredients.'?search=Unused&usage=used')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($ingredients.'?search=Caffeine&usage=used')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::ENDPOINT.'?search='.str_repeat('x', 151))
            ->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_admin_can_view_package_and_exact_ingredient_strengths(): void
    {
        $this->seed(DemoMedicineSeeder::class);
        $package = Medicine::where('name_en', 'Mawjood Demo Package')->sole();
        $suspension = Medicine::where('name_en', 'Demo Paracetamol Suspension')->sole();
        $this->actingAs($this->admin(), 'web');

        $this->getJson(self::ENDPOINT.'/'.$package->id)->assertOk()
            ->assertJsonPath('data.pack_size', '12.000')
            ->assertJsonPath('data.catalog_status', 'active')
            ->assertJsonCount(2, 'data.ingredients')
            ->assertJsonPath('data.ingredients.0.name_en', 'Paracetamol')
            ->assertJsonPath('data.ingredients.0.strength_value', '500.0000')
            ->assertJsonPath('data.ingredients.1.name_en', 'Caffeine')
            ->assertJsonPath('data.ingredients.1.strength_value', '65.0000');

        $this->getJson(self::ENDPOINT.'/'.$suspension->id)->assertOk()
            ->assertJsonPath('data.ingredients.0.strength_value', '120.0000')
            ->assertJsonPath('data.ingredients.0.strength_unit', 'mg')
            ->assertJsonPath('data.ingredients.0.per_value', '5.0000')
            ->assertJsonPath('data.ingredients.0.per_unit', 'mL');

        $this->getJson(self::ENDPOINT.'/999999')->assertNotFound();
        $this->getJson(self::ENDPOINT.'/'.$package->id.'abc')->assertNotFound();
    }

    public function test_customers_cannot_access_catalog_endpoints(): void
    {
        $this->seed(DemoMedicineSeeder::class);
        $id = Medicine::query()->value('id');
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->actingAs($customer, 'web')->getJson(self::ENDPOINT)->assertForbidden();
        $this->getJson(self::ENDPOINT.'/'.$id)->assertForbidden();
    }

    public function test_guest_cannot_list_medicines(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    private function admin(): User
    {
        return User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
    }
}
