<?php

namespace Tests\Feature;

use App\Enums\InventoryBatchStatus;
use App\Enums\PharmacyStatus;
use App\Models\InventoryItem;
use App\Models\Medicine;
use App\Models\Pharmacy;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMedicineSearchTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/medicines/search';

    public function test_guests_only_see_listed_packages_with_available_stock_at_active_pharmacies(): void
    {
        $this->seed(DemoMedicineSeeder::class);
        $medicine = Medicine::where('name_en', 'Demo Paracetamol Tablets')->sole();
        $listed = $this->listing($medicine, 'Public', PharmacyStatus::Active);
        $today = today('Asia/Amman');
        $this->batch($listed, InventoryBatchStatus::Active, $today->toDateString(), 10, 3);
        $this->batch($listed, InventoryBatchStatus::Active, $today->copy()->addDay()->toDateString(), 5, 1);
        $this->batch($listed, InventoryBatchStatus::Active, $today->copy()->subDay()->toDateString(), 50);
        foreach ([InventoryBatchStatus::Quarantined, InventoryBatchStatus::Damaged, InventoryBatchStatus::Recalled, InventoryBatchStatus::Inactive] as $status) {
            $this->batch($listed, $status, $today->toDateString(), 50);
        }

        $fullyHeld = $this->listing($medicine, 'Fully held', PharmacyStatus::Active);
        $this->batch($fullyHeld, InventoryBatchStatus::Active, $today->toDateString(), 6, 6);
        $this->batch($fullyHeld, InventoryBatchStatus::Quarantined, $today->toDateString(), 30);
        $expired = $this->listing($medicine, 'Expired', PharmacyStatus::Active);
        $this->batch($expired, InventoryBatchStatus::Active, $today->copy()->subDay()->toDateString(), 5);
        $this->listing($medicine, 'No batches', PharmacyStatus::Active);

        foreach ([
            $this->listing($medicine, 'Unlisted', PharmacyStatus::Active, false),
            $this->listing($medicine, 'Pending', PharmacyStatus::Pending),
            $this->listing($medicine, 'Suspended', PharmacyStatus::Suspended),
        ] as $excluded) {
            $this->batch($excluded, InventoryBatchStatus::Active, $today->toDateString(), 10);
        }

        $this->getJson(self::ENDPOINT.'?'.http_build_query(['search' => 'باراسيتامول']))->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $listed->id)
            ->assertJsonPath('data.0.pharmacy.id', $listed->pharmacy_id)
            ->assertJsonPath('data.0.medicine.id', $medicine->id)
            ->assertJsonPath('data.0.available_quantity', 11)
            ->assertJsonPath('data.0.selling_price', '2.250')
            ->assertJsonPath('data.0.currency', 'JOD')
            ->assertJsonMissingPath('data.0.batches')
            ->assertJsonMissingPath('data.0.quantity_held');
    }

    public function test_guest_search_matches_shared_ingredients_and_handles_empty_results_and_invalid_input(): void
    {
        $this->seed(DemoMedicineSeeder::class);
        $medicine = Medicine::where('name_en', 'Mawjood Demo Package')->sole();
        $listed = $this->listing($medicine, 'Combination', PharmacyStatus::Active);
        $this->batch($listed, InventoryBatchStatus::Active, today('Asia/Amman')->addDay()->toDateString(), 2);

        $this->getJson(self::ENDPOINT.'?search=Caffeine')->assertOk()
            ->assertJsonPath('data.0.medicine.id', $medicine->id)
            ->assertJsonPath('data.0.available_quantity', 2)
            ->assertJsonPath('meta.per_page', 10);
        $this->getJson(self::ENDPOINT.'?search=no-matching-package')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::ENDPOINT.'?search=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::ENDPOINT)->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson(self::ENDPOINT.'?search='.str_repeat('x', 151))->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    private function listing(Medicine $medicine, string $name, PharmacyStatus $status, bool $listed = true): InventoryItem
    {
        $pharmacy = new Pharmacy([
            'name_en' => $name, 'name_ar' => 'صيدلية', 'license_no' => 'PUBLIC-'.$name,
            'address' => 'Demo address', 'city' => 'Amman', 'latitude' => 31.95, 'longitude' => 35.91,
        ]);
        $pharmacy->status = $status;
        $pharmacy->save();

        return $pharmacy->inventoryItems()->create([
            'medicine_id' => $medicine->id, 'selling_price' => '2.250', 'is_listed' => $listed,
        ]);
    }

    private function batch(InventoryItem $item, InventoryBatchStatus $status, string $expires, int $onHand, int $held = 0): void
    {
        $item->batches()->create([
            'batch_no' => $status->value, 'status' => $status, 'expiry_date' => $expires,
            'quantity_on_hand' => $onHand, 'quantity_held' => $held,
        ]);
    }
}
