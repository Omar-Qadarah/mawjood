<?php

namespace Tests\Feature;

use App\Enums\PharmacyStatus;
use App\Enums\PlatformRole;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
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
            ->assertJsonPath('data.matches.0.same_english_name', true)
            ->assertJsonPath('data.matches.0.same_arabic_name', true)
            ->assertJsonPath('data.matches.0.license_no', 'MATCH')
            ->assertJsonPath('data.matches.0.address', 'Same address')
            ->assertJsonPath('data.matches.0.status', 'active')
            ->assertJsonPath('data.matches.0.same_address', true);
        $this->assertSame(PharmacyStatus::Pending, $pharmacy->fresh()->status);
        $this->getJson('/api/v1/admin/pharmacies/'.$related->id.'/review-summary')->assertConflict();
    }

    public function test_ai_receives_only_aggregate_matches_and_completeness_flags(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response([
            'done' => true,
            'message' => ['role' => 'assistant', 'content' => 'Compare the possible overlaps before approval.'],
        ])]);

        $pending = $this->pharmacy('PENDING');
        $pending->update(['phone' => '+962790123456', 'email' => 'fake@example.test']);
        $this->pharmacy('BOTH');
        $this->pharmacy('NAME')->update(['address' => 'A different address']);
        $this->pharmacy('ADDRESS')->update(['name_en' => 'Another pharmacy', 'name_ar' => 'صيدلية مختلفة']);

        $path = '/api/v1/admin/pharmacies/'.$pending->id.'/review-summary';
        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]), 'web');
        $this->getJson($path)->assertOk()->assertJsonPath('data.source', 'directory');
        Http::assertNothingSent();

        $this->postJson($path)->assertOk()->assertJsonPath('data.source', 'ai')
            ->assertJsonPath('data.summary', 'Compare the possible overlaps before approval.')
            ->assertJsonCount(3, 'data.matches')->assertJsonMissingPath('data.signals');
        Http::assertSentCount(1);
        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'http://127.0.0.1:11434/api/chat'
            && $request['model'] === 'qwen2.5:1.5b'
            && $request['stream'] === false
            && json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR) === [
                'matches' => ['total' => 3, 'name_only' => 1, 'address_only' => 1, 'name_and_address' => 1],
                'completeness' => ['contact_provided' => true, 'opening_hours_provided' => false],
            ]);
        $this->assertSame(PharmacyStatus::Pending, $pending->fresh()->status);
    }

    public function test_directory_summary_is_returned_when_provider_is_unavailable(): void
    {
        Http::preventStrayRequests();
        Http::fake(['http://127.0.0.1:11434/api/chat' => Http::response([], 503)]);

        $pending = $this->pharmacy('PENDING');
        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]), 'web');
        $this->postJson('/api/v1/admin/pharmacies/'.$pending->id.'/review-summary')->assertOk()
            ->assertJsonPath('data.source', 'directory')->assertJsonCount(0, 'data.matches');
    }

    public function test_only_a_pending_application_can_be_approved_from_the_review(): void
    {
        $pending = $this->pharmacy('APPROVAL');
        $suspended = $this->pharmacy('SUSPENDED');
        $suspended->forceFill(['status' => PharmacyStatus::Suspended])->save();
        $path = '/api/v1/admin/pharmacies/'.$pending->id.'/approve';

        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]), 'web');
        $this->postJson($path)->assertForbidden();
        $this->assertSame(PharmacyStatus::Pending, $pending->fresh()->status);

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]), 'web');
        $this->postJson($path)->assertNoContent();
        $this->assertSame(PharmacyStatus::Active, $pending->fresh()->status);
        $this->postJson($path)->assertConflict();
        $this->postJson('/api/v1/admin/pharmacies/'.$suspended->id.'/approve')->assertConflict();
        $this->assertSame(PharmacyStatus::Suspended, $suspended->fresh()->status);
    }

    public function test_pharmacy_sort_applies_before_pagination_and_rejects_unknown_columns(): void
    {
        foreach (range(1, 21) as $number) {
            $this->pharmacy(sprintf('SORT-%02d', $number));
        }

        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]), 'web');
        $path = '/api/v1/admin/pharmacies?search=SORT-';

        $this->getJson($path.'&sort=license&direction=asc&page=2')->assertOk()
            ->assertJsonPath('meta.total', 21)->assertJsonPath('data.0.license_no', 'SORT-21');
        $this->getJson($path.'&sort=license&direction=desc&page=2')->assertOk()
            ->assertJsonPath('data.0.license_no', 'SORT-01');
        $this->getJson($path.'&sort=unknown')->assertUnprocessable()->assertJsonValidationErrors('sort');
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
