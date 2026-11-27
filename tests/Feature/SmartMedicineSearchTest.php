<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmartMedicineSearchTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/medicines/smart-search';

    public function test_smart_search_uses_catalog_availability_and_logs_only_metadata(): void
    {
        config(['services.openai.key' => 'test-key']);
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $this->seed([DemoMedicineSeeder::class, DemoInventorySeeder::class]);
        $query = 'Do you stock paracetmol tablets?';
        Http::fake(['api.openai.com/*' => Http::response($this->modelReply('Demo Paracetamol Tablets'))]);

        $this->actingAs($admin, 'web')->postJson(self::URL, ['search' => $query])->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.medicine.name_en', 'Demo Paracetamol Tablets')
            ->assertJsonPath('data.0.available_quantity', 72);
        Http::assertSent(fn ($request) => $request['input'] === $query && $request['store'] === false);
        $this->assertDatabaseHas('ai_requests', [
            'user_id' => $admin->id, 'feature' => 'smart_search', 'status' => 'ok',
            'prescription_id' => null, 'input_tokens' => 65, 'output_tokens' => 12, 'cost_usd' => null,
        ]);
        $this->assertDatabaseCount('ai_requests', 1);
    }

    public function test_rejections_and_provider_failures_are_logged_without_provider_messages(): void
    {
        config(['services.openai.key' => null]);
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push($this->modelReply(null))->push(['error' => 'private sample content'], 500)]);
        $this->postJson(self::URL)->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->postJson(self::URL, ['search' => 'Paracetamol'])->assertServiceUnavailable();
        Http::assertNothingSent();

        config(['services.openai.key' => 'test-key']);
        $this->postJson(self::URL, ['search' => 'What can I take for a headache?'])->assertUnprocessable();
        $this->postJson(self::URL, ['search' => 'Paracetamol'])->assertStatus(502)->assertDontSee('private sample content');
        foreach ([
            'invalid_input' => 'rejected',
            'provider_not_configured' => 'rejected',
            'no_medicine_named' => 'rejected',
            'provider_error' => 'failed',
        ] as $error => $status) {
            $this->assertDatabaseHas('ai_requests', [
                'feature' => 'smart_search', 'status' => $status, 'error_code' => $error, 'user_id' => null,
            ]);
        }
        $this->assertDatabaseCount('ai_requests', 4);
    }

    public function test_throttled_requests_are_logged_without_calling_the_provider(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();

        for ($request = 0; $request < 10; $request++) {
            $this->postJson(self::URL, ['search' => 'Paracetamol'])->assertServiceUnavailable();
        }

        $this->postJson(self::URL, ['search' => 'Paracetamol'])->assertTooManyRequests();
        $this->assertDatabaseHas('ai_requests', ['feature' => 'smart_search', 'status' => 'rejected', 'error_code' => 'rate_limited']);
        $this->assertDatabaseCount('ai_requests', 11);
        Http::assertNothingSent();
    }

    private function modelReply(?string $term): array
    {
        return [
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [
                ['type' => 'output_text', 'text' => json_encode(['term' => $term], JSON_THROW_ON_ERROR)],
            ]]],
            'usage' => ['input_tokens' => 65, 'output_tokens' => 12],
        ];
    }
}
