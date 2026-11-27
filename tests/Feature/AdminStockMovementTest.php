<?php

namespace Tests\Feature;

use App\Enums\InventoryBatchStatus;
use App\Enums\PlatformRole;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminStockMovementTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/inventory-items/';

    public function test_receiving_opens_a_batch_and_retries_do_not_duplicate_the_receipt(): void
    {
        [$admin, $item] = $this->stock();
        $this->actingAs($admin, 'web');
        $base = self::ENDPOINT.$item->id;
        $payload = [
            'batch_no' => 'TEST-RECEIPT',
            'expiry_date' => today('Asia/Amman')->addYear()->toDateString(),
            'quantity' => 10,
            'reason' => 'Supplier delivery',
            'request_key' => (string) Str::uuid(),
        ];

        $receipt = $this->postJson($base.'/receipts', $payload)->assertCreated()
            ->assertJsonPath('data.type', 'receipt')->assertJsonPath('data.quantity', 10)
            ->assertJsonPath('data.created_by', $admin->id)->json('data.id');
        $this->postJson($base.'/receipts', $payload)->assertOk()->assertJsonPath('data.id', $receipt);
        $this->postJson($base.'/receipts', array_replace($payload, ['quantity' => 3]))->assertConflict();

        unset($payload['request_key']);
        $this->postJson($base.'/receipts', array_replace($payload, ['quantity' => 2]))->assertCreated();
        $this->postJson($base.'/receipts', array_replace($payload, [
            'expiry_date' => today('Asia/Amman')->addMonths(6)->toDateString(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('expiry_date');
        $this->postJson($base.'/receipts', array_replace($payload, [
            'batch_no' => 'EXPIRED-RECEIPT', 'expiry_date' => today('Asia/Amman')->subDay()->toDateString(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('expiry_date');

        $batch = $item->batches()->where('batch_no', 'TEST-RECEIPT')->sole();
        $this->assertSame(12, $batch->quantity_on_hand);
        $this->assertSame(InventoryBatchStatus::Active, $batch->status);
        $this->assertCount(2, $batch->movements);
        $this->getJson($base)->assertOk()->assertJsonPath('data.available_quantity', 84)
            ->assertJsonPath('data.movements.0.batch_no', 'TEST-RECEIPT')
            ->assertJsonPath('data.movements.0.quantity', 2);
    }

    public function test_adjustment_preserves_holds_and_is_scoped_to_the_inventory_item(): void
    {
        [$admin, $item] = $this->stock();
        $this->actingAs($admin, 'web');
        $batch = $item->batches()->where('batch_no', 'DEMO-PAR-01')->sole();
        $batch->update(['quantity_held' => 12]);
        $url = self::ENDPOINT.$item->id.'/batches/'.$batch->id.'/adjustments';
        $payload = ['quantity' => -36, 'reason' => 'Count correction', 'request_key' => (string) Str::uuid()];

        $this->postJson($url, $payload)->assertCreated()
            ->assertJsonPath('data.type', 'adjustment')->assertJsonPath('data.quantity', -36);
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, ['quantity' => -1, 'reason' => 'Would reduce held stock'])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson($url, ['quantity' => 0, 'reason' => 'No movement'])->assertUnprocessable();
        $this->postJson($url, ['quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $other = InventoryItem::whereKeyNot($item->id)->firstOrFail();
        $this->postJson(self::ENDPOINT.$other->id.'/batches/'.$batch->id.'/adjustments', ['quantity' => 1, 'reason' => 'Wrong listing'])
            ->assertNotFound();

        $this->assertSame(12, $batch->fresh()->quantity_on_hand);
        $this->assertSame(12, $batch->fresh()->quantity_held);
        $this->assertSame(1, $batch->movements()->where('type', 'adjustment')->count());
    }

    public function test_customers_cannot_record_movements(): void
    {
        [, $item] = $this->stock();
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->actingAs($customer, 'web');
        $batch = $item->batches()->value('id');

        $this->postJson(self::ENDPOINT.$item->id.'/receipts')->assertForbidden();
        $this->postJson(self::ENDPOINT.$item->id.'/batches/'.$batch.'/adjustments')->assertForbidden();
        $this->assertDatabaseCount('inventory_movements', 4);
    }

    private function stock(): array
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $this->seed(DemoMedicineSeeder::class);
        $this->seed(DemoInventorySeeder::class);

        $item = InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Tablets'))->sole();

        return [$admin, $item];
    }
}
