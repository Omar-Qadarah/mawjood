<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgePrescriptionImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_preserves_active_reservation_images_and_existing_audit_history(): void
    {
        config(['prescriptions.purge_enabled' => true]);
        $disk = Storage::fake('prescriptions');
        $this->freezeSecond();
        $user = User::factory()->create();
        $pharmacy = Pharmacy::create([
            'name_en' => 'Demo Pharmacy', 'name_ar' => 'صيدلية', 'license_no' => 'DEMO-PURGE',
            'address' => 'Demo Address', 'city' => 'Amman', 'latitude' => 31.95, 'longitude' => 35.91,
        ]);

        $prescriptions = [];
        foreach (['due', 'future', 'pending', 'confirmed', 'closed', 'missing'] as $case) {
            $prescriptions[$case] = Prescription::create([
                'user_id' => $user->id, 'status' => 'uploaded', 'image_path' => $case.'.png',
                'delete_after' => $case === 'future' ? now()->addSecond() : now(),
            ]);
            if ($case !== 'missing') {
                $disk->put($case.'.png', 'synthetic image');
            }
        }

        $pending = $this->reservation($prescriptions['pending'], $pharmacy, ReservationStatus::Pending, 'pending');
        $confirmed = $this->reservation($prescriptions['confirmed'], $pharmacy, ReservationStatus::Confirmed, 'confirmed');
        $closed = $this->reservation($prescriptions['closed'], $pharmacy, ReservationStatus::Completed, 'closed');
        DB::table('audit_logs')->insert([
            'actor_id' => $user->id, 'action' => 'prescription.image_viewed', 'subject_type' => Prescription::class,
            'subject_id' => $prescriptions['closed']->id, 'created_at' => now(),
        ]);

        $this->artisan('prescriptions:purge-images')->expectsOutput('Purged 3 prescription image(s).')->assertSuccessful();
        foreach (['due', 'closed', 'missing'] as $case) {
            $record = $prescriptions[$case]->fresh();
            $disk->assertMissing($case.'.png');
            $this->assertNull($record->image_path);
            $this->assertTrue($record->image_deleted_at->equalTo(now()));
        }
        foreach (['future', 'pending', 'confirmed'] as $case) {
            $disk->assertExists($case.'.png');
            $this->assertNull($prescriptions[$case]->fresh()->image_deleted_at);
        }

        $pending->update(['status' => ReservationStatus::Cancelled]);
        $confirmed->update(['status' => ReservationStatus::Completed]);
        $this->artisan('prescriptions:purge-images')->expectsOutput('Purged 2 prescription image(s).')->assertSuccessful();
        $this->artisan('prescriptions:purge-images')->expectsOutput('Purged 0 prescription image(s).')->assertSuccessful();
        $disk->assertMissing(['pending.png', 'confirmed.png']);
        $this->assertSame($prescriptions['closed']->id, $closed->fresh()->prescription_id);
        $this->assertDatabaseCount('prescriptions', 6);
        $this->assertDatabaseHas('audit_logs', ['action' => 'prescription.image_viewed', 'subject_id' => $prescriptions['closed']->id]);
        $this->assertSame(5, DB::table('audit_logs')->where('action', 'prescription.image_purged')->count());
    }

    public function test_purge_is_disabled_until_enabled(): void
    {
        config(['prescriptions.purge_enabled' => false]);
        $disk = Storage::fake('prescriptions');
        $disk->put('due.png', 'synthetic image');
        $prescription = Prescription::create([
            'user_id' => User::factory()->create()->id, 'status' => 'uploaded',
            'image_path' => 'due.png', 'delete_after' => now()->subDay(),
        ]);

        $this->artisan('prescriptions:purge-images')->expectsOutput('Prescription image purging is disabled.')->assertSuccessful();
        $disk->assertExists('due.png');
        $this->assertNull($prescription->fresh()->image_deleted_at);
    }

    private function reservation(Prescription $prescription, Pharmacy $pharmacy, ReservationStatus $status, string $code): Reservation
    {
        return Reservation::create([
            'code' => $code,
            'user_id' => $prescription->user_id,
            'pharmacy_id' => $pharmacy->id,
            'prescription_id' => $prescription->id,
            'status' => $status,
            'idempotency_key' => $code,
        ]);
    }
}
