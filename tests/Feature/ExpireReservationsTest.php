<?php

namespace Tests\Feature;

use App\Actions\Reservation\CreateReservationAction;
use App\Enums\BatchAllocationStatus;
use App\Enums\PlatformRole;
use App\Enums\ReservationStatus;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\TestCase;

class ExpireReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiry_releases_only_due_pending_holds_and_is_safe_to_repeat(): void
    {
        [$customer, $item] = $this->stock();
        $start = now()->startOfMinute();
        $this->travelTo($start);

        $due = $this->reserve($customer, $item, 'due', 50);
        $confirmed = $this->reserve($customer, $item, 'confirmed', 5);
        $confirmed->update(['status' => ReservationStatus::Confirmed]);

        $this->travelTo($start->copy()->addMinutes(29));
        $future = $this->reserve($customer, $item, 'future', 3);
        $this->artisan('reservations:expire')->expectsOutput('Expired 0 reservation(s).')->assertSuccessful();
        $this->assertSame(58, (int) $item->batches()->sum('quantity_held'));

        $deadline = $start->copy()->addMinutes(30);
        $this->travelTo($deadline);
        $this->artisan('reservations:expire')->expectsOutput('Expired 1 reservation(s).')->assertSuccessful();

        $this->assertSame(ReservationStatus::Expired, $due->fresh()->status);
        $this->assertTrue($deadline->equalTo($due->fresh()->status_changed_at));
        $allocations = $due->allocations()->get();
        $this->assertCount(2, $allocations);
        $this->assertSame(50, $allocations->sum('quantity'));
        foreach ($allocations as $allocation) {
            $this->assertSame(BatchAllocationStatus::Released, $allocation->status);
            $this->assertSame('response_deadline', $allocation->release_reason);
            $this->assertTrue($deadline->equalTo($allocation->released_at));
        }

        $this->assertSame(ReservationStatus::Confirmed, $confirmed->fresh()->status);
        $this->assertSame(ReservationStatus::Pending, $future->fresh()->status);
        $this->assertSame(8, (int) $item->batches()->sum('quantity_held'));
        $this->assertSame(72, (int) $item->batches()->sum('quantity_on_hand'));
        $this->assertDatabaseCount('inventory_movements', 4);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $due->id, 'action' => 'reservation.expired', 'actor_id' => null]);

        $this->artisan('reservations:expire')->expectsOutput('Expired 0 reservation(s).')->assertSuccessful();
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'reservation.expired')->count());
        $this->getJson('/api/v1/medicines/search?search=Demo+Paracetamol+Tablets')
            ->assertOk()->assertJsonPath('data.0.available_quantity', 64);
    }

    public function test_expiry_rolls_back_inconsistent_holds_and_continues_processing_other_reservations(): void
    {
        Exceptions::fake();
        [$customer, $item] = $this->stock();
        $invalid = $this->reserve($customer, $item, 'inconsistent', 5);
        $invalid->allocations()->sole()->batch->update(['quantity_held' => 0]);

        $another = InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Suspension'))->sole();
        $valid = $this->reserve($customer, $another, 'valid', 2);

        $this->travel(31)->minutes();
        $this->artisan('reservations:expire')
            ->expectsOutput('Could not expire reservation '.$invalid->id.'.')
            ->expectsOutput('Expired 1 reservation(s).')->assertFailed();

        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame(ReservationStatus::Pending, $invalid->fresh()->status);
        $this->assertSame(BatchAllocationStatus::Held, $invalid->allocations()->sole()->status);
        $this->assertSame(ReservationStatus::Expired, $valid->fresh()->status);
        $this->assertSame(0, (int) $another->batches()->sum('quantity_held'));
    }

    private function stock(): array
    {
        User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->seed(DemoMedicineSeeder::class);
        $this->seed(DemoInventorySeeder::class);
        $item = InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Tablets'))->sole();

        return [$customer, $item];
    }

    private function reserve(User $customer, InventoryItem $item, string $key, int $quantity): Reservation
    {
        return app(CreateReservationAction::class)->execute($customer, [
            'pharmacy_id' => $item->pharmacy_id,
            'idempotency_key' => $key,
            'items' => [['inventory_item_id' => $item->id, 'quantity' => $quantity]],
        ]);
    }
}
