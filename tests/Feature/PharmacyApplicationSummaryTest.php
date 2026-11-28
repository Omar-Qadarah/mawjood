<?php

namespace Tests\Feature;

use App\Enums\PharmacyStatus;
use App\Enums\PlatformRole;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyApplicationSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_directory_matches_without_changing_the_application(): void
    {
        $pharmacy = $this->pharmacy('PENDING');
        $related = $this->pharmacy('MATCH');
        $related->forceFill(['status' => PharmacyStatus::Active])->save();
        $path = '/api/v1/admin/pharmacies/'.$pharmacy->id.'/review-summary';

        $this->getJson($path)->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]), 'web');
        $this->getJson($path)->assertOk()->assertJsonCount(1, 'data.matches')
            ->assertJsonPath('data.matches.0.id', $related->id)
            ->assertJsonPath('data.matches.0.same_name', true)
            ->assertJsonPath('data.matches.0.same_address', true);
        $this->assertSame(PharmacyStatus::Pending, $pharmacy->fresh()->status);
        $this->getJson('/api/v1/admin/pharmacies/'.$related->id.'/review-summary')->assertConflict();
    }

    private function pharmacy(string $license): Pharmacy
    {
        return Pharmacy::create([
            'name_en' => 'Test Pharmacy', 'name_ar' => 'صيدلية تجريبية', 'license_no' => $license,
            'governorate' => 'Amman', 'city' => 'Amman', 'address' => 'Same address',
            'latitude' => 31.95, 'longitude' => 35.91,
        ]);
    }
}
