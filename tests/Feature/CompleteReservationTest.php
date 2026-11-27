<?php

namespace Tests\Feature;

use App\Actions\Reservation\CreateReservationAction;
use App\Actions\Reservation\IssuePickupCodeAction;
use App\Actions\Reservation\ReviewReservationAction;
use App\Enums\PlatformRole;
use App\Enums\ReservationStatus;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompleteReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private InventoryItem $item;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $this->customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->seed([DemoMedicineSeeder::class, DemoInventorySeeder::class]);
        $this->item = InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Tablets'))->sole();
        $pending = app(CreateReservationAction::class)->execute($this->customer, [
            'pharmacy_id' => $this->item->pharmacy_id,
            'idempotency_key' => 'collection',
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 50]],
        ]);
        $this->reservation = app(ReviewReservationAction::class)->execute($pending, $this->admin, ReservationStatus::Confirmed, null);
    }

    public function test_owner_gets_code_once_and_admin_records_one_dispense_per_batch(): void
    {
        $code = $this->actingAs($this->customer, 'web')->postJson($this->issueUrl())->assertCreated()->json('data.pickup_code');
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->assertNotSame($code, $this->reservation->fresh()->pickup_code_hash);
        $this->postJson($this->issueUrl())->assertConflict();
        $this->postJson($this->completeUrl(), ['pickup_code' => $code])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'web');
        $this->getJson('/api/v1/admin/pharmacy-sites')->assertOk()
            ->assertJsonPath('data.0.completed_reservations', 0)->assertJsonCount(0, 'data.0.sales_by_currency');
        $this->postJson($this->completeUrl(), ['pickup_code' => $code])->assertOk()
            ->assertJsonPath('data.status', 'completed')->assertJsonPath('data.held_quantity', 0)
            ->assertJsonPath('data.items.0.allocations.0.status', 'fulfilled')
            ->assertJsonPath('data.items.0.allocations.1.status', 'fulfilled')
            ->assertJsonMissingPath('data.pickup_code_hash');
        $this->getJson('/api/v1/admin/pharmacy-sites')->assertOk()
            ->assertJsonPath('data.0.completed_reservations', 1)
            ->assertJsonPath('data.0.sales_by_currency.0.currency', 'JOD')
            ->assertJsonPath('data.0.sales_by_currency.0.amount', '112.500');
        $this->assertSame(22, (int) $this->item->batches()->sum('quantity_on_hand'));
        $this->assertSame(0, (int) $this->item->batches()->sum('quantity_held'));
        $this->assertNull($this->reservation->fresh()->pickup_code_hash);
        $this->postJson($this->completeUrl(), ['pickup_code' => $code])->assertConflict();

        $moves = DB::table('inventory_movements')->where('reservation_id', $this->reservation->id)->orderBy('quantity')->get();
        $this->assertSame([-48, -2], $moves->pluck('quantity')->all());
        $this->assertSame(['dispense', 'dispense'], $moves->pluck('type')->all());
        $this->assertSame([$this->admin->id, $this->admin->id], $moves->pluck('created_by')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'reservation.completed', 'actor_id' => $this->admin->id, 'subject_id' => $this->reservation->id]);
    }

    public function test_other_users_cannot_request_the_pickup_code(): void
    {
        $this->postJson($this->issueUrl())->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'web')->postJson($this->issueUrl())->assertNotFound();
    }

    public function test_attempt_limit_and_pickup_deadline_prevent_dispense(): void
    {
        $this->reservation->forceFill(['pickup_code_hash' => $this->reservation->hashPickupCode('000123')])->save();
        $this->actingAs($this->admin, 'web');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson($this->completeUrl(), ['pickup_code' => '000124'])->assertConflict();
        }
        $this->postJson($this->completeUrl(), ['pickup_code' => '000123'])->assertTooManyRequests();
        $this->assertSame(5, $this->reservation->fresh()->pickup_attempts);

        $this->travelTo($this->reservation->pickup_deadline);
        $this->postJson($this->completeUrl(), ['pickup_code' => '000123'])->assertConflict();
        $this->assertSame(72, (int) $this->item->batches()->sum('quantity_on_hand'));
        $this->assertSame(50, (int) $this->item->batches()->sum('quantity_held'));
        $this->assertDatabaseMissing('inventory_movements', ['type' => 'dispense']);
    }

    public function test_admin_cancels_pending_and_confirmed_reservations_and_releases_only_their_holds(): void
    {
        $pending = app(CreateReservationAction::class)->execute($this->customer, [
            'pharmacy_id' => $this->item->pharmacy_id,
            'idempotency_key' => 'cancel-pending',
            'items' => [['inventory_item_id' => $this->item->id, 'quantity' => 5]],
        ]);
        $code = app(IssuePickupCodeAction::class)->execute($this->reservation, $this->customer);
        $this->actingAs($this->admin, 'web');

        foreach ([[$pending, 50], [$this->reservation, 0]] as [$reservation, $remaining]) {
            $url = '/api/v1/admin/reservations/'.$reservation->id.'/cancel';
            $this->postJson($url)->assertOk()
                ->assertJsonPath('data.status', 'cancelled')
                ->assertJsonPath('data.held_quantity', 0)
                ->assertJsonPath('data.items.0.allocations.0.status', 'released');
            $this->assertSame(['cancelled'], $reservation->allocations()->pluck('release_reason')->unique()->values()->all());
            $this->assertSame($remaining, (int) $this->item->batches()->sum('quantity_held'));
            $this->assertSame(72, (int) $this->item->batches()->sum('quantity_on_hand'));
            $this->assertDatabaseHas('audit_logs', [
                'action' => 'reservation.cancelled', 'actor_id' => $this->admin->id, 'subject_id' => $reservation->id,
            ]);
            $this->postJson($url)->assertConflict();
        }

        $this->assertNull($this->reservation->fresh()->pickup_code_hash);
        $this->postJson($this->completeUrl(), ['pickup_code' => $code])->assertConflict();
        $this->assertDatabaseCount('inventory_movements', 4);
        $this->getJson('/api/v1/admin/reservations?status=cancelled')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_guests_and_customers_cannot_cancel_from_the_admin_api(): void
    {
        $url = '/api/v1/admin/reservations/'.$this->reservation->id.'/cancel';
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs($this->customer, 'web')->postJson($url)->assertForbidden();
        $this->assertSame(ReservationStatus::Confirmed, $this->reservation->fresh()->status);
        $this->assertSame(50, (int) $this->item->batches()->sum('quantity_held'));
    }

    private function issueUrl(): string
    {
        return '/api/v1/reservations/'.$this->reservation->id.'/pickup-code';
    }

    private function completeUrl(): string
    {
        return '/api/v1/admin/reservations/'.$this->reservation->id.'/complete';
    }
}
