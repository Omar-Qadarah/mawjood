<?php

namespace Tests\Feature;

use App\Actions\Reservation\CreateReservationAction;
use App\Enums\InventoryBatchStatus;
use App\Enums\PlatformRole;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $customer;

    private InventoryItem $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfSecond());
        User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $this->owner = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->seed([DemoMedicineSeeder::class, DemoInventorySeeder::class]);
        $this->stock = InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Tablets'))->sole();
        $this->owner->pharmacies()->attach($this->stock->pharmacy_id, ['role' => 'owner', 'is_active' => true]);
    }

    public function test_confirmation_keeps_stock_held_until_pickup_deadline_then_expires(): void
    {
        $reservation = $this->reserve(50, 'confirm');
        $this->actingAs($this->owner, 'web');
        $this->travel(5)->minutes();
        $pickup = now()->addHours(3);

        $this->patchJson($this->endpoint($reservation), ['status' => 'confirmed'])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.status_changed_at', now()->toISOString())
            ->assertJsonPath('data.pickup_deadline', $pickup->toISOString())
            ->assertJsonPath('data.held_quantity', 50);
        $this->assertSame($this->owner->id, $reservation->fresh()->handled_by);
        $this->assertSame(50, (int) $this->stock->batches()->sum('quantity_held'));
        $this->assertDatabaseHas('audit_logs', [
            'subject_id' => $reservation->id, 'actor_id' => $this->owner->id, 'action' => 'reservation.confirmed',
        ]);

        $this->patchJson($this->endpoint($reservation), ['status' => 'rejected'])->assertConflict();
        $this->travelTo($pickup->copy()->subSecond());
        $this->artisan('reservations:expire')->expectsOutput('Expired 0 reservation(s).')->assertSuccessful();
        $this->travelTo($pickup);
        $this->artisan('reservations:expire')->expectsOutput('Expired 1 reservation(s).')->assertSuccessful();
        $this->getJson($this->endpoint($reservation))->assertOk()
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.held_quantity', 0);
        $this->assertSame(0, (int) $this->stock->batches()->sum('quantity_held'));
        $this->assertSame(['pickup_deadline'], $reservation->allocations()->pluck('release_reason')->unique()->values()->all());
    }

    public function test_rejection_releases_only_the_reservations_own_holds_and_records_the_reason(): void
    {
        $rejected = $this->reserve(50, 'reject');
        $other = $this->reserve(5, 'other');
        $this->actingAs($this->owner, 'web');

        $this->patchJson($this->endpoint($rejected), ['status' => 'rejected', 'rejection_reason' => 'Cannot prepare order'])
            ->assertOk()->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Cannot prepare order')
            ->assertJsonPath('data.held_quantity', 0)
            ->assertJsonPath('data.pickup_deadline', null);
        $this->assertSame(['rejected'], $rejected->allocations()->pluck('release_reason')->unique()->values()->all());
        $this->assertSame(2, $rejected->allocations()->whereNotNull('released_at')->count());
        $this->assertSame($this->owner->id, $rejected->fresh()->handled_by);
        $this->assertSame(5, (int) $this->stock->batches()->sum('quantity_held'));
        $this->assertSame(72, (int) $this->stock->batches()->sum('quantity_on_hand'));
        $this->assertDatabaseCount('inventory_movements', 4);
        $this->getJson($this->endpoint($other))->assertOk()->assertJsonPath('data.held_quantity', 5);
        $this->getJson('/api/v1/medicines/search?search=Demo+Paracetamol+Tablets')
            ->assertOk()->assertJsonPath('data.0.available_quantity', 67);

        $this->patchJson($this->endpoint($rejected), ['status' => 'rejected'])->assertConflict();
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'reservation.rejected')
            ->where('actor_id', $this->owner->id)->where('subject_id', $rejected->id)->count());
    }

    public function test_confirmation_replaces_a_quarantined_batch_when_stock_is_available(): void
    {
        $reservation = $this->reserve(5, 'replace');
        $original = $reservation->allocations()->sole();
        $original->batch->update(['status' => InventoryBatchStatus::Quarantined]);
        $this->actingAs($this->owner, 'web');

        $this->patchJson($this->endpoint($reservation), ['status' => 'confirmed'])->assertOk()
            ->assertJsonPath('data.held_quantity', 5)
            ->assertJsonPath('data.items.0.allocations.0.status', 'released')
            ->assertJsonPath('data.items.0.allocations.1.batch_no', 'DEMO-PAR-02')
            ->assertJsonPath('data.items.0.allocations.1.status', 'held');
        $this->assertSame(0, $original->batch->fresh()->quantity_held);
        $this->assertSame('ineligible_batch', $original->fresh()->release_reason);
    }

    public function test_confirmation_flags_unreplaceable_stock_and_can_be_retried_after_receiving_stock(): void
    {
        $reservation = $this->reserve(50, 'shortage');
        $original = $reservation->allocations()->oldest('batch_allocations.id')->firstOrFail();
        $original->batch->update(['status' => InventoryBatchStatus::Quarantined]);
        $this->actingAs($this->owner, 'web');

        $this->patchJson($this->endpoint($reservation), ['status' => 'confirmed'])->assertConflict();
        $this->getJson($this->endpoint($reservation))->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.attention_reason', 'insufficient_eligible_stock')
            ->assertJsonPath('data.held_quantity', 24);
        $this->assertSame(0, $original->batch->fresh()->quantity_held);

        $this->postJson('/api/v1/pharmacy-operations/inventory-items/'.$this->stock->id.'/receipts', [
            'batch_no' => 'DEMO-REPLACEMENT',
            'expiry_date' => today('Asia/Amman')->addMonth()->toDateString(),
            'quantity' => 30,
        ])->assertCreated();
        $this->patchJson($this->endpoint($reservation), ['status' => 'confirmed'])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.attention_reason', null)
            ->assertJsonPath('data.held_quantity', 50);
    }

    public function test_non_members_cannot_review_reservations(): void
    {
        $endpoint = $this->endpoint($this->reserve(5, 'access'));
        $this->patchJson($endpoint, ['status' => 'confirmed'])->assertUnauthorized();
        $this->actingAs($this->customer, 'web')->patchJson($endpoint, ['status' => 'rejected'])->assertForbidden();
    }

    public function test_review_enforces_valid_decisions_and_the_response_deadline(): void
    {
        $reservation = $this->reserve(5, 'late');
        $endpoint = $this->endpoint($reservation);
        $this->actingAs($this->owner, 'web')->patchJson($endpoint, ['status' => 'completed'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson($endpoint, ['status' => 'confirmed', 'rejection_reason' => 'Unexpected reason'])
            ->assertUnprocessable()->assertJsonValidationErrors('rejection_reason');
        $this->travelTo($reservation->response_deadline);
        $this->patchJson($endpoint, ['status' => 'confirmed'])->assertConflict();
        $this->patchJson($endpoint, ['status' => 'rejected'])->assertConflict();
        $this->assertSame(5, (int) $this->stock->batches()->sum('quantity_held'));
        $this->artisan('reservations:expire')->expectsOutput('Expired 1 reservation(s).')->assertSuccessful();
        $this->assertSame(0, (int) $this->stock->batches()->sum('quantity_held'));
    }

    private function reserve(int $quantity, string $key): Reservation
    {
        return app(CreateReservationAction::class)->execute($this->customer, [
            'pharmacy_id' => $this->stock->pharmacy_id,
            'idempotency_key' => $key,
            'items' => [['inventory_item_id' => $this->stock->id, 'quantity' => $quantity]],
        ]);
    }

    private function endpoint(Reservation $reservation): string
    {
        return '/api/v1/pharmacy-operations/reservations/'.$reservation->id;
    }
}
