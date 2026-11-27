<?php

namespace Tests\Feature;

use App\Enums\PharmacyStatus;
use App\Enums\PlatformRole;
use App\Enums\ReservationStatus;
use App\Models\Pharmacy;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_totals_and_the_five_newest_pharmacies(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $createdAt = now();
        $ids = [];
        for ($number = 1; $number <= 6; $number++) {
            $pharmacy = new Pharmacy([
                'name_ar' => 'صيدلية الاختبار',
                'name_en' => 'Test Pharmacy '.$number,
                'license_no' => 'DASHBOARD-'.$number,
                'address' => 'Test Address',
                'city' => 'Amman',
                'latitude' => 31.95,
                'longitude' => 35.93,
            ]);
            $pharmacy->created_at = $createdAt->copy()->subDays($number <= 2 ? 0 : $number);
            $pharmacy->status = $number === 2 ? PharmacyStatus::Active : PharmacyStatus::Pending;
            $pharmacy->save();
            $ids[$number] = $pharmacy->id;
        }

        $customer = User::factory()->create();
        foreach ([ReservationStatus::Completed, ReservationStatus::Pending] as $status) {
            Reservation::create([
                'code' => $status->value,
                'user_id' => $customer->id,
                'pharmacy_id' => $ids[2],
                'status' => $status,
                'idempotency_key' => $status->value,
            ]);
        }

        $this->actingAs($admin, 'web')
            ->getJson('/api/v1/admin/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('counts', ['total' => 6, 'pending' => 5, 'active' => 1, 'suspended' => 0])
            ->assertJsonPath('completed_reservations_count', 1)
            ->assertJsonCount(5, 'recent_pharmacies')
            ->assertJsonPath('recent_pharmacies.0.id', $ids[2])
            ->assertJsonPath('recent_pharmacies.1.id', $ids[1])
            ->assertJsonPath('recent_pharmacies.4.id', $ids[5]);
    }

    public function test_customer_cannot_view_dashboard_summary(): void
    {
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);

        $this->actingAs($customer, 'web')
            ->getJson('/api/v1/admin/dashboard/summary')
            ->assertForbidden();
    }

    public function test_guest_cannot_view_dashboard_summary(): void
    {
        $this->getJson('/api/v1/admin/dashboard/summary')->assertUnauthorized();
    }
}
