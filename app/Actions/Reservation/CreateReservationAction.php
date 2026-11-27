<?php

namespace App\Actions\Reservation;

use App\Enums\BatchAllocationStatus;
use App\Enums\PlatformRole;
use App\Enums\ReservationStatus;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Pharmacy;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateReservationAction
{
    public function execute(User $user, array $data): Reservation
    {
        $pharmacyId = (int) $data['pharmacy_id'];
        $prescriptionId = isset($data['prescription_id']) ? (int) $data['prescription_id'] : null;
        $note = $data['customer_note'] ?? null;
        $quantities = collect($data['items'])
            ->mapWithKeys(fn (array $line) => [(int) $line['inventory_item_id'] => (int) $line['quantity']])
            ->sortKeys(SORT_NUMERIC)->all();

        return DB::transaction(function () use ($user, $data, $pharmacyId, $prescriptionId, $note, $quantities): Reservation {
            // Serialize retry and active-reservation checks for this customer.
            $customer = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($customer->is_active && $customer->platform_role === PlatformRole::Customer, 403);

            $customerReservations = Reservation::query()->where('user_id', $customer->id);
            $prior = (clone $customerReservations)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($prior !== null) {
                $prior->load('items');
                $previousQuantities = $prior->items->pluck('quantity', 'inventory_item_id')->sortKeys(SORT_NUMERIC)->all();
                abort_unless(
                    (int) $prior->pharmacy_id === $pharmacyId
                        && ($prior->prescription_id === null ? null : (int) $prior->prescription_id) === $prescriptionId
                        && $prior->customer_note === $note && $previousQuantities === $quantities,
                    409,
                    'This idempotency key was already used for a different reservation.',
                );

                return $prior;
            }

            abort_if($customerReservations->whereIn('status', [
                ReservationStatus::Pending->value, ReservationStatus::Confirmed->value,
            ])->count() >= 3, 409, 'You already have three active reservations.');

            if (! Pharmacy::query()->searchable()->sharedLock()->find($pharmacyId, ['id'])) {
                throw ValidationException::withMessages(['pharmacy_id' => 'Choose an active pharmacy.']);
            }

            if ($prescriptionId !== null && ! DB::table('prescriptions')->where('id', $prescriptionId)
                ->where('user_id', $customer->id)->whereNull('image_deleted_at')->sharedLock()->first(['id'])) {
                throw ValidationException::withMessages(['prescription_id' => 'Choose one of your prescriptions.']);
            }

            // Lock all listings by ID before reading or locking any of their batches.
            $items = InventoryItem::query()->with('medicine')
                ->where('pharmacy_id', $pharmacyId)->where('is_listed', true)
                ->whereKey(array_keys($quantities))->orderBy('id')->lockForUpdate()->get();
            if ($items->count() !== count($quantities)) {
                throw ValidationException::withMessages(['items' => 'Each item must be listed at the selected pharmacy.']);
            }

            $now = now();
            $date = $now->copy()->setTimezone('Asia/Amman')->toDateString();
            $stock = [];
            foreach ($items as $item) {
                $batches = $item->batches()->eligibleOn($date)->whereColumn('quantity_on_hand', '>', 'quantity_held')
                    ->orderBy('id')->lockForUpdate()->get();
                $available = $batches->sum(fn (InventoryBatch $batch) => $batch->quantity_on_hand - $batch->quantity_held);
                abort_if($available < $quantities[$item->id], 409, 'Insufficient available stock for '.$item->medicine->name_en.'.');
                $stock[$item->id] = $batches->sortBy([['expiry_date', 'asc'], ['id', 'asc']]);
            }

            $reservation = $this->createWithUniqueCode([
                'user_id' => $customer->id,
                'pharmacy_id' => $pharmacyId,
                'prescription_id' => $prescriptionId,
                'status' => ReservationStatus::Pending,
                'status_changed_at' => $now,
                'response_deadline' => $now->copy()->addMinutes(30),
                'idempotency_key' => $data['idempotency_key'],
                'customer_note' => $note,
            ]);

            foreach ($items as $item) {
                $remaining = $quantities[$item->id];
                $medicine = $item->medicine;
                $line = $reservation->items()->create([
                    'pharmacy_id' => $pharmacyId,
                    'inventory_item_id' => $item->id,
                    'medicine_name_snapshot' => Str::substr(implode(' · ', [
                        $medicine->name_en, $medicine->strength_text, $medicine->dosage_form,
                        $medicine->pack_size.' '.$medicine->pack_unit,
                    ]), 0, 255),
                    'quantity' => $remaining,
                    'unit_price' => $item->selling_price,
                ]);

                foreach ($stock[$item->id] as $batch) {
                    $held = min($remaining, $batch->quantity_on_hand - $batch->quantity_held);
                    $batch->increment('quantity_held', $held);
                    $line->allocations()->create([
                        'inventory_item_id' => $item->id,
                        'batch_id' => $batch->id,
                        'quantity' => $held,
                        'status' => BatchAllocationStatus::Held,
                    ]);
                    $remaining -= $held;
                    if ($remaining === 0) {
                        break;
                    }
                }
            }

            DB::table('audit_logs')->insert([
                'actor_id' => $customer->id,
                'action' => 'reservation.created',
                'subject_type' => Reservation::class,
                'subject_id' => $reservation->id,
                'new_values' => json_encode(['status' => ReservationStatus::Pending->value], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return $reservation->load('items');
        }, attempts: 3);
    }

    private function createWithUniqueCode(array $attributes): Reservation
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return Reservation::create(['code' => Str::upper(Str::random(10))] + $attributes);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 3) {
                    throw $exception;
                }
            }
        }
    }
}
