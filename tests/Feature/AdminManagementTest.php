<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Enums\PlatformRole;
use App\Models\Membership;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
    }

    private function pharmacyData(): array
    {
        return [
            'name_ar' => 'صيدلية الاختبار',
            'name_en' => 'Test Pharmacy',
            'license_no' => 'ADMIN-TEST',
            'address' => 'Test Address',
            'governorate' => 'Amman',
            'city' => 'Sahab',
            'latitude' => 31.87,
            'longitude' => 36.00,
        ];
    }

    public function test_admin_can_create_an_active_pharmacy_with_jordanian_details(): void
    {
        $slot = ['date' => '2026-12-01', 'opens_at' => '09:00', 'closes_at' => '13:00'];
        $id = $this->actingAs($this->admin(), 'web')
            ->postJson('/api/v1/admin/pharmacies', $this->pharmacyData() + [
                'status' => 'active',
                'phone' => '079 123 4567',
                'opening_hours' => [$slot],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.phone', '+962791234567')
            ->assertJsonPath('data.opening_hours.0', $slot)
            ->json('data.id');

        $this->getJson('/api/v1/admin/pharmacies?governorate=Amman&city=Sahab')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/admin/pharmacies/'.$id)
            ->assertJsonPath('data.governorate', 'Amman');
    }

    public function test_pharmacy_validation_rejects_bad_phone_city_and_hours(): void
    {
        $this->actingAs($this->admin(), 'web');
        $payload = $this->pharmacyData();
        $this->postJson('/api/v1/admin/pharmacies', array_replace($payload, [
            'city' => 'Irbid',
            'phone' => '123',
            'opening_hours' => [['date' => '2026-12-01', 'opens_at' => '13:00', 'closes_at' => '09:00']],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['phone', 'opening_hours.0.closes_at']);

        $this->postJson('/api/v1/admin/pharmacies', array_replace($payload, ['city' => 'Irbid']))
            ->assertUnprocessable()->assertJsonValidationErrors('city');

        $this->postJson('/api/v1/admin/pharmacies', $payload + ['opening_hours' => [
            ['date' => '2026-12-01', 'opens_at' => '09:00', 'closes_at' => '13:00'],
            ['date' => '2026-12-01', 'opens_at' => '12:00', 'closes_at' => '15:00'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('opening_hours');
    }

    public function test_admin_can_manage_customers_without_deleting_owners(): void
    {
        $admin = $this->admin();
        $base = '/api/v1/admin/customers';
        $id = $this->actingAs($admin, 'web')->postJson($base, [
            'name' => 'Demo Customer',
            'email' => 'demo-customer@example.com',
            'password' => 'Password123',
        ])->assertCreated()->assertJsonMissingPath('data.password')->json('data.id');

        $this->getJson($base.'?search=Demo&status=active')->assertJsonPath('data.0.id', $id);
        $this->patchJson($base.'/'.$id, ['is_active' => false])->assertJsonPath('data.is_active', false);
        $this->getJson($base.'/'.$admin->id)->assertNotFound();

        $pharmacy = Pharmacy::create($this->pharmacyData());
        $membership = new Membership(['user_id' => $id, 'pharmacy_id' => $pharmacy->id]);
        $membership->role = MembershipRole::Owner;
        $membership->save();
        $this->getJson($base.'/'.$id)->assertJsonPath('data.memberships.0.role', 'owner');
        $this->deleteJson($base.'/'.$id)->assertConflict();
        $membership->delete();
        $this->deleteJson($base.'/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    public function test_customer_cannot_access_the_customer_management_api(): void
    {
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);

        $this->actingAs($customer, 'web')->getJson('/api/v1/admin/customers')->assertForbidden();
    }
}
