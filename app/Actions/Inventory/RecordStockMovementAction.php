<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryBatchStatus;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockMovementAction
{
    public function execute(InventoryItem $item, int $actorId, array $data, ?int $batchId = null): InventoryMovement
    {
        return DB::transaction(function () use ($item, $actorId, $data, $batchId): InventoryMovement {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $type = $batchId === null ? 'receipt' : 'adjustment';
            $quantity = (int) $data['quantity'];
            $reason = $data['reason'] ?? null;
            $reference = isset($data['request_key']) ? 'admin-stock:'.strtolower($data['request_key']) : null;

            if ($reference !== null) {
                $previous = $item->movements()->with('batch')->where('reference', $reference)->first();
                if ($previous !== null) {
                    $sameBatch = $batchId === null
                        ? $previous->batch->batch_no === $data['batch_no'] && $previous->batch->expiry_date->toDateString() === $data['expiry_date']
                        : (int) $previous->batch_id === $batchId;

                    abort_unless(
                        $sameBatch && $previous->type === $type && $previous->quantity === $quantity
                            && $previous->reason === $reason && (int) $previous->created_by === $actorId,
                        409,
                        'This request key was already used for a different stock movement.',
                    );

                    return $previous;
                }
            }

            $batch = $batchId === null
                ? $this->receiptBatch($item, $data)
                : $item->batches()->lockForUpdate()->findOrFail($batchId);

            $balance = $batch->quantity_on_hand + $quantity;
            if ($balance < $batch->quantity_held) {
                throw ValidationException::withMessages([
                    'quantity' => 'The new stock balance cannot be lower than the quantity already held.',
                ]);
            }
            if ($balance > 4294967295) {
                throw ValidationException::withMessages(['quantity' => 'The resulting stock balance exceeds the batch limit.']);
            }

            $batch->quantity_on_hand = $balance;
            $batch->save();

            return $batch->movements()->create([
                'type' => $type,
                'quantity' => $quantity,
                'reason' => $reason,
                'reference' => $reference,
                'created_by' => $actorId,
            ])->setRelation('batch', $batch);
        }, attempts: 3);
    }

    private function receiptBatch(InventoryItem $item, array $data): InventoryBatch
    {
        if ($data['expiry_date'] < today('Asia/Amman')->toDateString()) {
            throw ValidationException::withMessages(['expiry_date' => 'An expired batch cannot receive stock.']);
        }

        $matches = $item->batches()->where('batch_no', $data['batch_no'])->lockForUpdate()->limit(2)->get();
        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['batch_no' => 'More than one batch uses this lot number.']);
        }

        $batch = $matches->first();
        if ($batch !== null) {
            if ($batch->expiry_date->toDateString() !== $data['expiry_date']) {
                throw ValidationException::withMessages(['expiry_date' => 'Use the expiry date recorded for this batch number.']);
            }

            return $batch;
        }

        return $item->batches()->make([
            'batch_no' => $data['batch_no'],
            'expiry_date' => $data['expiry_date'],
            'quantity_on_hand' => 0,
            'quantity_held' => 0,
            'status' => InventoryBatchStatus::Active,
        ]);
    }
}
