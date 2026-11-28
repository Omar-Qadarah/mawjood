<?php

namespace Tests\Feature;

use App\Actions\Reservation\CreateReservationAction;
use App\Enums\PlatformRole;
use App\Enums\ReservationStatus;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReservationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/reservations';

    public function test_admin_can_filter_reservation_status_and_deadlines(): void
    {
        $reservation = $this->createReservation();
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $this->actingAs($admin, 'web');

        $deadline = $reservation->response_deadline->toISOString();
        $this->getJson(self::ENDPOINT.'?status=pending')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', $reservation->code)
            ->assertJsonMissingPath('data.0.held_quantity')
            ->assertJsonPath('data.0.response_deadline', $deadline)
            ->assertJsonMissingPath('data.0.items');
        $this->getJson(self::ENDPOINT.'?status=expired')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::ENDPOINT.'?status=unknown')->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->getJson(self::ENDPOINT.'/'.$reservation->id)->assertNotFound();
        $this->patchJson(self::ENDPOINT.'/'.$reservation->id, ['status' => 'confirmed'])->assertNotFound();
        $this->patchJson('/api/v1/pharmacy-operations/reservations/'.$reservation->id, ['status' => 'confirmed'])->assertForbidden();

        $this->travelTo($reservation->response_deadline);
        $this->artisan('reservations:expire')->assertSuccessful();
        $this->getJson(self::ENDPOINT.'?status=pending')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::ENDPOINT.'?status=expired')->assertOk()->assertJsonPath('data.0.status', 'expired');
    }

    public function test_admin_can_view_confirmed_pickup_deadline(): void
    {
        $reservation = $this->createReservation();
        $pickup = now()->addHours(3)->startOfSecond();
        $reservation->forceFill(['status' => ReservationStatus::Confirmed, 'pickup_deadline' => $pickup])->save();
        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]), 'web');

        $this->getJson(self::ENDPOINT.'?status=confirmed')->assertOk()
            ->assertJsonPath('data.0.status', 'confirmed')
            ->assertJsonPath('data.0.pickup_deadline', $pickup->toISOString())
            ->assertJsonMissingPath('data.0.held_quantity');
        $this->getJson(self::ENDPOINT.'/999999')->assertNotFound();
    }

    public function test_governorate_filter_combines_with_completed_status(): void
    {
        $completed = $this->createReservation();
        $completed->pharmacy->update(['governorate' => 'Amman']);
        $completed->update(['status' => ReservationStatus::Completed]);
        $aqaba = Pharmacy::create([
            'name_en' => 'Aqaba Preview', 'name_ar' => 'العقبة', 'license_no' => 'AQ-RESERVATIONS',
            'address' => 'Test address', 'city' => 'Aqaba', 'governorate' => 'Aqaba', 'latitude' => 29.55, 'longitude' => 35,
        ]);
        foreach ([[$aqaba->id, ReservationStatus::Completed], [$completed->pharmacy_id, ReservationStatus::Pending]] as [$pharmacyId, $status]) {
            Reservation::create([
                'code' => 'RES-'.$status->value[0], 'user_id' => $completed->user_id,
                'pharmacy_id' => $pharmacyId, 'status' => $status, 'idempotency_key' => $status->value,
            ]);
        }
        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]), 'web');
        $this->getJson(self::ENDPOINT.'?status=completed')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson(self::ENDPOINT.'?status=completed&governorate=Amman')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $completed->id);
        $this->getJson(self::ENDPOINT.'?'.http_build_query(['status' => 'completed', 'governorate' => 'Amman', 'search' => $completed->code]))
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::ENDPOINT.'?status=completed&search=Aqaba')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.pharmacy.id', $aqaba->id);
        $this->getJson(self::ENDPOINT.'?search='.rawurlencode('%'))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::ENDPOINT.'?governorate=unknown')->assertUnprocessable()->assertJsonValidationErrors('governorate');
    }

    public function test_dashboard_and_deadline_filter_use_the_same_upcoming_reservations(): void
    {
        $this->freezeTime();
        $pending = $this->createReservation();
        $now = now();
        $pending->update(['response_deadline' => $now->copy()->addHour()]);

        foreach ([
            ['PICKUP', ReservationStatus::Confirmed, -5, 10],
            ['PASSED', ReservationStatus::Pending, 0, null],
            ['FUTURE', ReservationStatus::Pending, 61, null],
            ['DONE', ReservationStatus::Completed, 20, 20],
        ] as [$code, $status, $responseMinutes, $pickupMinutes]) {
            Reservation::create([
                'code' => $code, 'user_id' => $pending->user_id, 'pharmacy_id' => $pending->pharmacy_id,
                'status' => $status, 'idempotency_key' => $code,
                'response_deadline' => $now->copy()->addMinutes($responseMinutes),
            ])->forceFill([
                'pickup_deadline' => $pickupMinutes === null ? null : $now->copy()->addMinutes($pickupMinutes),
            ])->save();
        }

        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]), 'web');
        $this->getJson('/api/v1/admin/dashboard/summary')->assertOk()->assertJsonPath('upcoming_reservations_count', 2);
        $this->getJson(self::ENDPOINT.'?deadline=upcoming')->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.code', 'PICKUP')
            ->assertJsonPath('data.1.id', $pending->id);
        $this->getJson(self::ENDPOINT.'?deadline=upcoming&status=pending')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $pending->id);
    }

    public function test_only_active_admins_can_read_reservations(): void
    {
        $reservation = $this->createReservation();

        $this->getJson(self::ENDPOINT)->assertUnauthorized();

        $this->actingAs($reservation->user, 'web');
        $this->getJson(self::ENDPOINT)->assertForbidden();

        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => false]), 'web');
        $this->getJson(self::ENDPOINT)->assertForbidden();
    }

    private function createReservation(): Reservation
    {
        User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->seed(DemoMedicineSeeder::class);
        $this->seed(DemoInventorySeeder::class);
        $item = InventoryItem::whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Tablets'))->sole();

        return app(CreateReservationAction::class)->execute($customer, [
            'pharmacy_id' => $item->pharmacy_id,
            'idempotency_key' => 'admin-reservations-test',
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 50]],
        ]);
    }
}
