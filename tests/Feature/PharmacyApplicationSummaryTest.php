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
            ->assertJsonPath('data.matches.0.same_address', true);
        $this->assertSame(PharmacyStatus::Pending, $pharmacy->fresh()->status);
        $this->getJson('/api/v1/admin/pharmacies/'.$related->id.'/review-summary')->assertConflict();
    }

    public function test_ai_receives_only_aggregate_matches_and_completeness_flags(): void
    {
        config(['services.openai.key' => 'fake-test-key']);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [
                ['type' => 'output_text', 'text' => 'Compare the possible overlaps before approval.'],
            ]]],
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
        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['store'] === false
            && json_decode($request['input'], true, flags: JSON_THROW_ON_ERROR) === [
                'matches' => ['total' => 3, 'name_only' => 1, 'address_only' => 1, 'name_and_address' => 1],
                'completeness' => ['contact_provided' => true, 'opening_hours_provided' => false],
            ]);
        $this->assertSame(PharmacyStatus::Pending, $pending->fresh()->status);
    }

    public function test_directory_summary_is_returned_when_provider_is_unavailable(): void
    {
        config(['services.openai.key' => 'fake-test-key']);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([], 503)]);

        $pending = $this->pharmacy('PENDING');
        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]), 'web');
        $this->postJson('/api/v1/admin/pharmacies/'.$pending->id.'/review-summary')->assertOk()
            ->assertJsonPath('data.source', 'directory')->assertJsonCount(0, 'data.matches');
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
