<?php

namespace Tests\Feature;

use App\Enums\InventoryBatchStatus;
use App\Enums\PlatformRole;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/inventory-items';

    public function test_admin_can_filter_by_pharmacy_and_search_for_a_batch_or_medicine(): void
    {
        $this->actingAs($this->seedInventory(), 'web');
        $demo = Pharmacy::where('license_no', 'MAWJOOD-DEMO-STOCK')->sole();
        $other = Pharmacy::create([
            'name_en' => 'Other pharmacy', 'name_ar' => 'صيدلية أخرى', 'license_no' => 'OTHER-INVENTORY',
            'address' => 'Other address', 'city' => 'Amman', 'latitude' => 31.95, 'longitude' => 35.91,
        ]);
        $medicineId = $demo->inventoryItems()->firstOrFail()->medicine_id;
        $other->inventoryItems()->create(['medicine_id' => $medicineId, 'selling_price' => '2.000', 'is_listed' => false]);

        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('meta.total', 4);
        $this->getJson(self::ENDPOINT.'?pharmacy_id='.$demo->id)->assertOk()
            ->assertJsonPath('meta.total', 3)->assertJsonMissingPath('data.0.batches');
        $this->getJson(self::ENDPOINT.'?pharmacy_id='.$other->id)->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.available_quantity', 0)
            ->assertJsonPath('data.0.is_listed', false);

        foreach (['DEMO-PAR-02', 'Demo Paracetamol Tablets'] as $search) {
            $query = http_build_query(['pharmacy_id' => $demo->id, 'search' => $search]);
            $this->getJson(self::ENDPOINT.'?'.$query)->assertOk()->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.medicine.name_en', 'Demo Paracetamol Tablets');
        }
        $this->getJson(self::ENDPOINT.'?search=OTHER-INVENTORY')->assertOk()
            ->assertJsonPath('data.0.pharmacy.id', $other->id);
        $this->getJson(self::ENDPOINT.'?pharmacy_id=999999')->assertUnprocessable()->assertJsonValidationErrors('pharmacy_id');
    }

    public function test_available_quantity_agrees_between_list_and_details_and_excludes_ineligible_stock(): void
    {
        $this->actingAs($this->seedInventory(), 'web');
        $item = InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Tablets'))->sole();
        $today = today('Asia/Amman');
        $item->batches()->where('batch_no', 'DEMO-PAR-01')->firstOrFail()->update([
            'expiry_date' => $today->toDateString(), 'quantity_held' => 5,
        ]);
        $item->batches()->create([
            'batch_no' => 'EXPIRED-TEST', 'expiry_date' => $today->copy()->subDay()->toDateString(),
            'quantity_on_hand' => 12, 'status' => InventoryBatchStatus::Active,
        ]);
        foreach ([InventoryBatchStatus::Damaged, InventoryBatchStatus::Recalled, InventoryBatchStatus::Inactive] as $status) {
            $item->batches()->create([
                'batch_no' => $status->value, 'expiry_date' => $today->copy()->addDay()->toDateString(),
                'quantity_on_hand' => 12, 'status' => $status,
            ]);
        }

        $this->getJson(self::ENDPOINT.'?search=DEMO-PAR-01')->assertOk()
            ->assertJsonPath('data.0.available_quantity', 67);
        $detail = $this->getJson(self::ENDPOINT.'/'.$item->id)->assertOk()
            ->assertJsonPath('data.available_quantity', 67)->assertJsonCount(6, 'data.batches');
        $batches = collect($detail->json('data.batches'))->keyBy('batch_no');
        $this->assertSame(43, $batches['DEMO-PAR-01']['available_quantity']);
        $this->assertSame(5, $batches['DEMO-PAR-01']['quantity_held']);
        foreach (['EXPIRED-TEST', 'damaged', 'recalled', 'inactive'] as $batchNo) {
            $this->assertSame(0, $batches[$batchNo]['available_quantity']);
        }

        $quarantined = InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Cetirizine Tablets'))->sole();
        $this->getJson(self::ENDPOINT.'/'.$quarantined->id)->assertOk()
            ->assertJsonPath('data.available_quantity', 0)
            ->assertJsonPath('data.batches.0.status', 'quarantined')
            ->assertJsonPath('data.batches.0.available_quantity', 0);
        $this->getJson(self::ENDPOINT.'/999999')->assertNotFound();
    }

    public function test_customer_cannot_access_inventory_list_or_batch_details(): void
    {
        $this->seedInventory();
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->actingAs($customer, 'web')->getJson(self::ENDPOINT)->assertForbidden();
        $this->getJson(self::ENDPOINT.'/'.InventoryItem::query()->value('id'))->assertForbidden();
    }

    public function test_guest_cannot_access_inventory(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
    }

    private function seedInventory(): User
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $this->seed(DemoMedicineSeeder::class);
        $this->seed(DemoInventorySeeder::class);

        return $admin;
    }
}
