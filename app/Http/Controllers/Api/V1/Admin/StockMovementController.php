<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Inventory\RecordStockMovementAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdjustStockRequest;
use App\Http\Requests\Api\V1\Admin\ReceiveStockRequest;
use App\Http\Resources\Api\V1\Admin\StockMovementResource;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;

class StockMovementController extends Controller
{
    public function receive(ReceiveStockRequest $request, InventoryItem $inventoryItem, RecordStockMovementAction $action): JsonResponse
    {
        $movement = $action->execute($inventoryItem, $request->user()->id, $request->validated());

        return StockMovementResource::make($movement)->response()->setStatusCode($movement->wasRecentlyCreated ? 201 : 200);
    }

    public function adjust(AdjustStockRequest $request, InventoryItem $inventoryItem, int $batch, RecordStockMovementAction $action): JsonResponse
    {
        $movement = $action->execute($inventoryItem, $request->user()->id, $request->validated(), $batch);

        return StockMovementResource::make($movement)->response()->setStatusCode($movement->wasRecentlyCreated ? 201 : 200);
    }
}
