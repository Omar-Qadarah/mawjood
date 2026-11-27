<?php

namespace Database\Seeders;

use App\Actions\Reservation\CancelReservationAction;
use App\Actions\Reservation\CompleteReservationAction;
use App\Actions\Reservation\CreateReservationAction;
use App\Actions\Reservation\IssuePickupCodeAction;
use App\Actions\Reservation\ReviewReservationAction;
use App\Enums\PharmacyStatus;
use App\Enums\PlatformRole;
use App\Enums\ReservationStatus;
use App\Models\ActiveIngredient;
use App\Models\Pharmacy;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminCrudDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Admin CRUD demo data can only be seeded in local and testing environments.');
        }

        $admin = User::where('platform_role', PlatformRole::Admin->value)->where('is_active', true)->oldest('id')->first();
        if ($admin === null) {
            throw new RuntimeException('Create an active admin with AdminUserSeeder before running AdminCrudDemoSeeder.');
        }

        DB::transaction(function () use ($admin): void {
            $this->call([DemoMedicineSeeder::class, DemoInventorySeeder::class]);

            ActiveIngredient::firstOrCreate(
                ['name_en' => 'Mawjood Demo Unused Ingredient'],
                ['name_ar' => 'مكون موجود التجريبي'],
            );

            $customer = $this->customer('Mawjood Demo Customer', 'mawjood.demo.customer@example.test', true);
            $this->customer('Mawjood Demo Inactive Customer', 'mawjood.demo.inactive@example.test', false);

            $this->pharmacy('IRBID', 'Mawjood Demo Irbid Pharmacy', 'صيدلية موجود إربد', 'Irbid', PharmacyStatus::Pending);
            $this->pharmacy('ZARQA', 'Mawjood Demo Zarqa Pharmacy', 'صيدلية موجود الزرقاء', 'Zarqa', PharmacyStatus::Suspended);

            $this->weeklyPharmacies();
            $this->historicalPharmacies();
            $this->reservations($customer, $admin);
        });
    }

    private function customer(string $name, string $email, bool $active): User
    {
        $customer = User::firstOrNew(['email' => $email]);
        if (! $customer->exists) {
            $customer->forceFill([
                'name' => $name,
                'password' => Str::random(40),
                'platform_role' => PlatformRole::Customer,
                'is_active' => $active,
            ])->save();
        }

        return $customer;
    }

    private function pharmacy(string $suffix, string $name, string $arabicName, string $governorate, PharmacyStatus $status, ?CarbonInterface $registeredAt = null): void
    {
        $pharmacy = Pharmacy::firstOrNew(['license_no' => 'MAWJOOD-DEMO-'.$suffix]);
        if ($pharmacy->exists) {
            return;
        }

        $place = config("jordan.{$governorate}");
        $pharmacy->forceFill([
            'name_en' => $name,
            'name_ar' => $arabicName,
            'city' => $place['cities'][0],
            'governorate' => $governorate,
            'address' => 'Demo location, '.$governorate,
            'latitude' => $place['center'][0],
            'longitude' => $place['center'][1],
            'status' => $status,
        ]);
        if ($registeredAt !== null) {
            $pharmacy->forceFill(['created_at' => $registeredAt, 'updated_at' => $registeredAt]);
        }
        $pharmacy->save();
    }

    private function weeklyPharmacies(): void
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $names = ['Cedar', 'Olive', 'Jasmine', 'Palm', 'Acacia'];
        $governorates = ['Amman', 'Irbid', 'Zarqa', 'Madaba', 'Aqaba', 'Jerash', 'Karak'];

        foreach ([3, 5, 2, 3, 1, 4, 2] as $daysAgo => $count) {
            $day = $today->copy()->subDays($daysAgo);
            $minutes = $daysAgo === 0 ? (int) $today->diffInMinutes($now) : 1080;
            $governorate = $governorates[$daysAgo];

            for ($slot = 0; $slot < $count; $slot++) {
                $registeredAt = $day->copy()->addMinutes(intdiv($minutes * ($slot + 1), $count + 1));
                $this->pharmacy(
                    "WEEK-{$daysAgo}-{$slot}",
                    "Mawjood Demo {$governorate} {$names[$slot]} Pharmacy",
                    'صيدلية موجود التجريبية',
                    $governorate,
                    $slot % 2 === 0 ? PharmacyStatus::Active : PharmacyStatus::Pending,
                    $registeredAt,
                );
            }
        }
    }

    private function historicalPharmacies(): void
    {
        $today = today();
        $governorates = array_keys(config('jordan'));

        foreach (range(7, 29) as $daysAgo) {
            $governorate = $governorates[($daysAgo - 7) % count($governorates)];
            $neighborhood = $daysAgo < 19 ? 'Junction' : 'Terrace';
            $this->pharmacy(
                "MONTH-{$daysAgo}",
                "Mawjood Demo {$governorate} {$neighborhood} Pharmacy",
                'صيدلية موجود التجريبية',
                $governorate,
                PharmacyStatus::Active,
                $today->copy()->subDays($daysAgo)->setTime(9 + $daysAgo % 8, ($daysAgo * 13) % 60),
            );
        }
    }

    private function reservations(User $customer, User $admin): void
    {
        $pharmacy = Pharmacy::where('license_no', 'MAWJOOD-DEMO-STOCK')->firstOrFail();
        $item = $pharmacy->inventoryItems()
            ->whereHas('medicine', fn ($query) => $query->where('name_en', 'Demo Paracetamol Tablets'))
            ->firstOrFail();

        foreach ([
            ReservationStatus::Completed,
            ReservationStatus::Rejected,
            ReservationStatus::Cancelled,
            ReservationStatus::Confirmed,
            ReservationStatus::Pending,
        ] as $status) {
            $key = 'mawjood-admin-demo-'.$status->value;
            if (Reservation::where('user_id', $customer->id)->where('idempotency_key', $key)->exists()) {
                continue;
            }

            $reservation = app(CreateReservationAction::class)->execute($customer, [
                'idempotency_key' => $key,
                'pharmacy_id' => $pharmacy->id,
                'items' => [['inventory_item_id' => $item->id, 'quantity' => 1]],
            ]);

            match ($status) {
                ReservationStatus::Completed => $this->complete($reservation, $customer, $admin),
                ReservationStatus::Rejected => app(ReviewReservationAction::class)
                    ->execute($reservation, $admin, $status, 'Demo rejection'),
                ReservationStatus::Cancelled => app(CancelReservationAction::class)->execute($reservation, $admin),
                ReservationStatus::Confirmed => app(ReviewReservationAction::class)->execute($reservation, $admin, $status, null),
                default => null,
            };
        }
    }

    private function complete(Reservation $reservation, User $customer, User $admin): void
    {
        $reservation = app(ReviewReservationAction::class)->execute($reservation, $admin, ReservationStatus::Confirmed, null);
        $code = app(IssuePickupCodeAction::class)->execute($reservation, $customer);
        app(CompleteReservationAction::class)->execute($reservation, $admin, $code);
    }
}

