<?php

namespace Tests\Feature;

use App\Enums\PharmacyStatus;
use App\Enums\PlatformRole;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_admin_can_activate_a_pending_pharmacy(): void
    {
        $admin = User::factory()->create([
            'platform_role' => PlatformRole::Admin,
            'is_active' => true,
        ]);
        $pharmacy = $this->createPendingPharmacy();

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/admin/pharmacies/{$pharmacy->id}", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertSame(PharmacyStatus::Active, $pharmacy->fresh()->status);
    }

    public function test_an_active_customer_cannot_activate_a_pending_pharmacy(): void
    {
        $customer = User::factory()->create([
            'platform_role' => PlatformRole::Customer,
            'is_active' => true,
        ]);
        $pharmacy = $this->createPendingPharmacy();

        $this->actingAs($customer, 'web')
            ->patchJson("/api/v1/admin/pharmacies/{$pharmacy->id}", ['status' => 'active'])
            ->assertForbidden();

        $this->assertSame(PharmacyStatus::Pending, $pharmacy->fresh()->status);
    }

    private function createPendingPharmacy(): Pharmacy
    {
        return Pharmacy::create([
            'name_ar' => 'صيدلية الاختبار',
            'name_en' => 'Test Pharmacy',
            'license_no' => 'APPROVAL-TEST',
            'address' => 'Test Address',
            'city' => 'Amman',
            'latitude' => 31.95,
            'longitude' => 35.93,
        ]);
    }
}
