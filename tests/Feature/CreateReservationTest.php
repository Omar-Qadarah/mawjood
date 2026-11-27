<?php

namespace Tests\Feature;

use App\Enums\InventoryBatchStatus;
use App\Enums\PharmacyStatus;
use App\Enums\PlatformRole;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreateReservationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/reservations';

    public function test_creation_holds_unexpired_stock_by_earliest_expiry_without_a_ledger_movement(): void
    {
        [$customer, $tablets, $liquid] = $this->stock();
        $this->actingAs($customer, 'web');
        $today = today('Asia/Amman');
        $later = $tablets->batches()->where('batch_no', 'DEMO-PAR-01')->sole();
        $earlier = $tablets->batches()->where('batch_no', 'DEMO-PAR-02')->sole();
        $later->update(['quantity_held' => 4, 'expiry_date' => $today->copy()->addDays(2)->toDateString()]);
        $earlier->update(['expiry_date' => $today->toDateString()]);
        foreach ([
            [InventoryBatchStatus::Active, $today->copy()->subDay()->toDateString()],
            [InventoryBatchStatus::Quarantined, $today->toDateString()],
        ] as [$status, $date]) {
            $tablets->batches()->create([
                'batch_no' => $status->value, 'status' => $status, 'expiry_date' => $date,
                'quantity_on_hand' => 100,
            ]);
        }

        $payload = $this->payload($tablets, 30);
        $payload['items'][] = ['inventory_item_id' => $liquid->id, 'quantity' => 2];
        $response = $this->postJson(self::ENDPOINT, $payload)->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonMissingPath('data.idempotency_key')
            ->assertJsonMissingPath('data.pickup_code_hash');
        $data = $response->json('data');
        $this->assertSame(10, strlen($data['code']));
        $this->assertSame(1800, strtotime($data['response_deadline']) - strtotime($data['status_changed_at']));
        $lines = collect($data['items'])->keyBy('inventory_item_id');
        $this->assertSame('2.250', $lines[$tablets->id]['unit_price']);
        $this->assertStringContainsString('Demo Paracetamol Tablets', $lines[$tablets->id]['medicine_name_snapshot']);

        $payload['items'] = array_reverse($payload['items']);
        $this->postJson(self::ENDPOINT, $payload)->assertOk()->assertJsonPath('data.id', $data['id']);
        $payload['items'][0]['quantity'] = 3;
        $this->postJson(self::ENDPOINT, $payload)->assertConflict();

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('reservation_items', 2);
        $this->assertDatabaseCount('batch_allocations', 3);
        $this->assertDatabaseHas('batch_allocations', ['batch_id' => $earlier->id, 'quantity' => 24, 'status' => 'held']);
        $this->assertDatabaseHas('batch_allocations', ['batch_id' => $later->id, 'quantity' => 6, 'status' => 'held']);
        $this->assertSame(48, $later->fresh()->quantity_on_hand);
        $this->assertSame(10, $later->fresh()->quantity_held);
        $this->assertSame(24, $earlier->fresh()->quantity_held);
        $this->assertDatabaseCount('inventory_movements', 4);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reservation.created', 'subject_id' => $data['id'], 'actor_id' => $customer->id]);

        $this->getJson('/api/v1/medicines/search?search=Demo+Paracetamol+Tablets')->assertOk()
            ->assertJsonPath('data.0.available_quantity', 38);
    }

    public function test_insufficient_stock_on_any_line_leaves_all_stock_and_records_unchanged(): void
    {
        [$customer, $tablets, $liquid] = $this->stock();
        $payload = $this->payload($tablets, 5);
        $payload['items'][] = ['inventory_item_id' => $liquid->id, 'quantity' => 100];

        $this->actingAs($customer, 'web')->postJson(self::ENDPOINT, $payload)->assertConflict();

        $this->assertSame(0, (int) $tablets->batches()->sum('quantity_held'));
        $this->assertSame(0, (int) $liquid->batches()->sum('quantity_held'));
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('reservation_items', 0);
        $this->assertDatabaseCount('batch_allocations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_customer_can_only_use_their_own_prescription_and_have_three_active_reservations(): void
    {
        [$customer, $tablets] = $this->stock();
        $other = User::factory()->create();
        $otherPrescription = DB::table('prescriptions')->insertGetId(['user_id' => $other->id, 'status' => 'uploaded']);
        $ownPrescription = DB::table('prescriptions')->insertGetId(['user_id' => $customer->id, 'status' => 'uploaded']);
        $this->actingAs($customer, 'web');

        $payload = $this->payload($tablets, 1);
        $this->postJson(self::ENDPOINT, $payload + ['prescription_id' => $otherPrescription])
            ->assertUnprocessable()->assertJsonValidationErrors('prescription_id');

        for ($number = 1; $number <= 3; $number++) {
            $payload['idempotency_key'] = 'request-'.$number;
            $this->postJson(self::ENDPOINT, $payload + ['prescription_id' => $ownPrescription])
                ->assertCreated()->assertJsonPath('data.prescription_id', $ownPrescription);
        }

        $this->postJson(self::ENDPOINT, $payload + ['prescription_id' => $ownPrescription])->assertOk();
        $payload['idempotency_key'] = 'request-four';
        $this->postJson(self::ENDPOINT, $payload)->assertConflict();
        $this->assertDatabaseCount('reservations', 3);
        $this->assertSame(3, (int) $tablets->batches()->sum('quantity_held'));
    }

    public function test_requests_cannot_mix_pharmacies_or_reserve_unlisted_stock(): void
    {
        [$customer, $tablets, $liquid] = $this->stock();
        $pharmacy = $tablets->pharmacy;
        $other = $pharmacy->replicate();
        $other->license_no = 'OTHER-TEST-PHARMACY';
        $other->save();
        $foreignItem = $other->inventoryItems()->create(['medicine_id' => $liquid->medicine_id, 'selling_price' => '1.000']);
        $this->actingAs($customer, 'web');

        $payload = $this->payload($tablets, 1);
        $payload['items'][] = ['inventory_item_id' => $foreignItem->id, 'quantity' => 1];
        $this->postJson(self::ENDPOINT, $payload)->assertUnprocessable()->assertJsonValidationErrors('items');
        $tablets->update(['is_listed' => false]);
        $this->postJson(self::ENDPOINT, $this->payload($tablets, 1))->assertUnprocessable()->assertJsonValidationErrors('items');
        $pharmacy->status = PharmacyStatus::Suspended;
        $pharmacy->save();
        $this->postJson(self::ENDPOINT, $this->payload($liquid, 1))->assertUnprocessable()->assertJsonValidationErrors('pharmacy_id');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_creation_requires_a_customer_session(): void
    {
        $this->postJson(self::ENDPOINT)->assertUnauthorized();
        [, $tablets] = $this->stock();
        $admin = User::where('platform_role', PlatformRole::Admin->value)->sole();
        $this->actingAs($admin, 'web')->postJson(self::ENDPOINT, $this->payload($tablets, 1))->assertForbidden();
    }

    private function stock(): array
    {
        User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->seed(DemoMedicineSeeder::class);
        $this->seed(DemoInventorySeeder::class);

        return [
            $customer,
            InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Tablets'))->sole(),
            InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Suspension'))->sole(),
        ];
    }

    private function payload(InventoryItem $item, int $quantity): array
    {
        return [
            'pharmacy_id' => $item->pharmacy_id,
            'idempotency_key' => 'request-one',
            'items' => [['inventory_item_id' => $item->id, 'quantity' => $quantity]],
        ];
    }
}
