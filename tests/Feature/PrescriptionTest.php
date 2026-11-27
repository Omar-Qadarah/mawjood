<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\User;
use Database\Seeders\DemoInventorySeeder;
use Database\Seeders\DemoMedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aH1sAAAAASUVORK5CYII=';

    public function test_customer_uploads_and_attaches_prescription_and_only_the_reservation_pharmacy_can_view_it(): void
    {
        Storage::fake('prescriptions');
        $this->freezeSecond();
        User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]);
        $customer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $viewer = User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]);
        $this->seed([DemoMedicineSeeder::class, DemoInventorySeeder::class]);
        $pharmacy = Pharmacy::where('license_no', 'MAWJOOD-DEMO-STOCK')->sole();
        $item = $pharmacy->inventoryItems()->where('is_listed', true)->firstOrFail();

        $bytes = base64_decode(self::PNG);
        $upload = $this->actingAs($customer, 'web')->post('/api/v1/prescriptions', [
            'image' => UploadedFile::fake()->createWithContent('original-name.png', $bytes),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.status', 'uploaded')
            ->assertJsonPath('data.delete_after', now()->addDays(3)->toISOString())
            ->assertJsonMissingPath('data.image_path');

        $prescription = Prescription::findOrFail($upload->json('data.id'));
        $this->assertSame($customer->id, $prescription->user_id);
        $this->assertNotSame('original-name.png', $prescription->image_path);
        Storage::disk('prescriptions')->assertExists($prescription->image_path);
        Storage::disk('public')->assertMissing($prescription->image_path);

        $payload = [
            'pharmacy_id' => $pharmacy->id,
            'idempotency_key' => 'prescription-upload',
            'prescription_id' => $prescription->id,
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1]],
        ];
        $reservation = $this->postJson('/api/v1/reservations', $payload)->assertCreated()
            ->assertJsonPath('data.prescription_id', $prescription->id);
        $id = $reservation->json('data.id');
        $url = '/api/v1/reservations/'.$id.'/prescription/image';
        $this->getJson($url)->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->actingAs($viewer, 'web');
        $this->postJson('/api/v1/reservations', $payload)->assertUnprocessable()->assertJsonValidationErrors('prescription_id');
        $other = Pharmacy::create([
            'name_en' => 'Another Pharmacy', 'name_ar' => 'صيدلية أخرى', 'license_no' => 'OTHER-RX',
            'address' => 'Demo Address', 'city' => 'Amman', 'latitude' => 31.95, 'longitude' => 35.91,
        ]);
        $viewer->pharmacies()->attach($other->id, ['role' => 'staff', 'is_active' => true]);
        $viewer->pharmacies()->attach($pharmacy->id, ['role' => 'staff', 'is_active' => false]);
        $this->getJson($url)->assertNotFound();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'prescription.image_viewed']);

        $viewer->pharmacies()->updateExistingPivot($pharmacy->id, ['is_active' => true]);
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($bytes, $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $viewer->id, 'action' => 'prescription.image_viewed',
            'subject_type' => Prescription::class, 'subject_id' => $prescription->id,
        ]);
        $viewer->pharmacies()->updateExistingPivot($pharmacy->id, ['role' => 'owner']);
        $this->assertSame($bytes, $this->get($url)->assertOk()->streamedContent());
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'prescription.image_viewed')->count());

        $prescription->forceFill(['image_deleted_at' => now()])->save();
        $this->getJson($url)->assertNotFound();
    }

    public function test_upload_rejects_non_images_and_requires_an_active_customer(): void
    {
        Storage::fake('prescriptions');
        $this->postJson('/api/v1/prescriptions')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Admin, 'is_active' => true]), 'web');
        $this->postJson('/api/v1/prescriptions')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->create(['platform_role' => PlatformRole::Customer, 'is_active' => true]), 'web');
        $this->post('/api/v1/prescriptions', [
            'image' => UploadedFile::fake()->createWithContent('fake.png', 'not an image'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertDatabaseCount('prescriptions', 0);
        $this->assertSame([], Storage::disk('prescriptions')->allFiles());
    }
}
