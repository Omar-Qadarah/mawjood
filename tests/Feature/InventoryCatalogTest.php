<?php

namespace Tests\Feature;

use App\Enums\InventoryBatchStatus;
use App\Enums\PharmacyStatus;
use App\Enums\PlatformRole;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_stock_uses_existing_packages_and_records_each_opening_receipt_once(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $this->seed(DemoMedicineSeeder::class);
        $this->seed(DemoInventorySeeder::class);
        $this->seed(DemoInventorySeeder::class);

        $pharmacy = Pharmacy::where('license_no', 'MAWJOOD-DEMO-STOCK')->sole();
        $items = $pharmacy->inventoryItems()->with(['medicine', 'batches'])->get()->keyBy('medicine.name_en');

        $this->assertSame(PharmacyStatus::Active, $pharmacy->status);
        $this->assertCount(3, $items);
        $this->assertDatabaseCount('medicines', 20);
        $this->assertDatabaseCount('inventory_batches', 4);
        $this->assertDatabaseCount('inventory_movements', 4);

        foreach ($items as $item) {
            $this->assertTrue($item->pharmacy->is($pharmacy));
            $this->assertTrue($item->is_listed);
            $this->assertNotNull($item->stock_verified_at);
            $this->assertTrue($item->medicine->inventoryItems()->whereKey($item->id)->exists());
            foreach ($item->batches as $batch) {
                $this->assertTrue($batch->inventoryItem->is($item));
                $this->assertSame(0, $batch->quantity_held);
                $this->assertTrue($batch->expiry_date->isFuture());
                $this->assertDatabaseHas('inventory_movements', [
                    'batch_id' => $batch->id,
                    'type' => 'receipt',
                    'quantity' => $batch->quantity_on_hand,
                    'created_by' => $admin->id,
                    'reservation_id' => null,
                ]);
            }
        }

        $this->assertCount(2, $items['Demo Paracetamol Tablets']->batches);
        $cetirizine = $items['Demo Cetirizine Tablets'];
        $this->assertSame('3.125', $cetirizine->selling_price);
        $this->assertSame(InventoryBatchStatus::Quarantined, $cetirizine->batches->sole()->status);
    }

    public function test_reseeding_keeps_adjusted_stock_and_its_ledger_history(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $this->seed(DemoMedicineSeeder::class);
        $this->seed(DemoInventorySeeder::class);

        $item = InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Tablets'))->sole();
        $batch = $item->batches()->where('batch_no', 'DEMO-PAR-01')->sole();
        $item->update(['selling_price' => '4.500', 'is_listed' => false]);
        $batch->update(['quantity_on_hand' => 47]);
        DB::table('inventory_movements')->insert([
            'batch_id' => $batch->id,
            'type' => 'adjustment',
            'quantity' => -1,
            'created_by' => $admin->id,
        ]);

        $this->seed(DemoInventorySeeder::class);

        $this->assertSame('4.500', $item->fresh()->selling_price);
        $this->assertFalse($item->fresh()->is_listed);
        $this->assertSame(47, $batch->fresh()->quantity_on_hand);
        $this->assertSame(47, (int) DB::table('inventory_movements')->where('batch_id', $batch->id)->sum('quantity'));
        $this->assertDatabaseCount('inventory_movements', 5);
    }
}
