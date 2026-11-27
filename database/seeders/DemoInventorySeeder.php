<?php

namespace Database\Seeders;

use App\Enums\InventoryBatchStatus;
use App\Enums\PharmacyStatus;
use App\Enums\PlatformRole;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoInventorySeeder extends Seeder
{
    public function run(): void
    {
        $stock = [
            'Demo Paracetamol Tablets' => [
                'selling_price' => '2.250',
                'batches' => [
                    ['batch_no' => 'DEMO-PAR-01', 'expiry_months' => 10, 'quantity' => 48, 'status' => InventoryBatchStatus::Active],
                    ['batch_no' => 'DEMO-PAR-02', 'expiry_months' => 16, 'quantity' => 24, 'status' => InventoryBatchStatus::Active],
                ],
            ],
            'Demo Paracetamol Suspension' => [
                'selling_price' => '2.750',
                'batches' => [
                    ['batch_no' => 'DEMO-SUS-01', 'expiry_months' => 9, 'quantity' => 16, 'status' => InventoryBatchStatus::Active],
                ],
            ],
            'Demo Cetirizine Tablets' => [
                'selling_price' => '3.125',
                'batches' => [
                    ['batch_no' => 'DEMO-CET-01', 'expiry_months' => 12, 'quantity' => 12, 'status' => InventoryBatchStatus::Quarantined],
                ],
            ],
        ];

        $medicines = Medicine::where('manufacturer_name', 'Mawjood Demo')
            ->whereIn('name_en', array_keys($stock))->get();
        $packagesByName = $medicines->keyBy('name_en');
        if ($medicines->count() !== count($stock) || $packagesByName->count() !== count($stock)) {
            throw new RuntimeException('DemoInventorySeeder requires one copy of each selected package from DemoMedicineSeeder.');
        }

        $actorId = User::where('platform_role', PlatformRole::Admin->value)->where('is_active', true)->oldest('id')->value('id');
        if ($actorId === null) {
            throw new RuntimeException('An active administrator is required to record the demo stock receipts.');
        }

        DB::transaction(function () use ($stock, $packagesByName, $actorId): void {
            $pharmacy = Pharmacy::where('license_no', 'MAWJOOD-DEMO-STOCK')->lockForUpdate()->first();
            if ($pharmacy && $pharmacy->name_en !== 'Mawjood Demo Stock Pharmacy') {
                throw new RuntimeException('The demo stock license is assigned to another pharmacy.');
            }

            if (! $pharmacy) {
                $pharmacy = new Pharmacy([
                    'license_no' => 'MAWJOOD-DEMO-STOCK',
                    'name_en' => 'Mawjood Demo Stock Pharmacy',
                    'name_ar' => 'صيدلية موجود التجريبية',
                    'address' => 'Demo location, Amman',
                    'governorate' => 'Amman',
                    'city' => 'Amman',
                    'latitude' => '31.953900',
                    'longitude' => '35.910600',
                ]);
                $pharmacy->status = PharmacyStatus::Active;
                $pharmacy->save();
            }

            $receivedAt = now();
            foreach ($stock as $name => $listing) {
                $item = $pharmacy->inventoryItems()->lockForUpdate()->firstOrCreate(
                    ['medicine_id' => $packagesByName[$name]->id],
                    ['selling_price' => $listing['selling_price'], 'is_listed' => true, 'stock_verified_at' => $receivedAt],
                );

                foreach ($listing['batches'] as $lot) {
                    $batch = $item->batches()->lockForUpdate()->firstOrCreate(
                        ['batch_no' => $lot['batch_no']],
                        [
                            'expiry_date' => $receivedAt->copy()->addMonthsNoOverflow($lot['expiry_months'])->toDateString(),
                            'quantity_on_hand' => $lot['quantity'],
                            'quantity_held' => 0,
                            'status' => $lot['status'],
                        ],
                    );

                    if ($batch->wasRecentlyCreated) {
                        DB::table('inventory_movements')->insert([
                            'batch_id' => $batch->id,
                            'type' => 'receipt',
                            'quantity' => $batch->quantity_on_hand,
                            'reason' => 'Demo opening stock',
                            'reference' => $batch->batch_no,
                            'created_by' => $actorId,
                            'created_at' => $receivedAt,
                        ]);
                    }
                }
            }
        }, attempts: 3);
    }
}
