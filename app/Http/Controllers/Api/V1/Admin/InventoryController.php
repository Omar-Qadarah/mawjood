<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SaveInventoryListingRequest;
use App\Http\Resources\Api\V1\Admin\InventoryItemResource;
use App\Models\InventoryItem;
use App\Support\AdminTableSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InventoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'pharmacy_id' => ['nullable', 'integer', 'min:1', 'exists:pharmacies,id'],
            'search' => ['nullable', 'string', 'max:150'],
            'listing' => ['nullable', 'in:listed,unlisted'],
            'stock' => ['nullable', 'in:low'],
        ]);

        $date = today('Asia/Amman')->toDateString();
        $items = InventoryItem::query()
            ->with(['pharmacy', 'medicine'])
            ->withCount('batches')
            ->withAvailableQuantity($date);

        if (isset($filters['pharmacy_id'])) {
            $items->where('pharmacy_id', $filters['pharmacy_id']);
        }

        if (isset($filters['listing'])) {
            $items->where('is_listed', $filters['listing'] === 'listed');
        }

        if (isset($filters['stock'])) {
            $items->lowStock($date);
        }

        if (isset($filters['search'])) {
            $pattern = '%'.strtr($filters['search'], ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $items->where(function (Builder $query) use ($pattern): void {
                $query->whereHas('pharmacy', function (Builder $pharmacy) use ($pattern): void {
                    $pharmacy->whereRaw("name_en LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("license_no LIKE ? ESCAPE '!'", [$pattern]);
                })->orWhereHas('medicine', function (Builder $medicine) use ($pattern): void {
                    $medicine->whereRaw("search_normalized LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("name_en LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern]);
                })->orWhereHas('batches', fn (Builder $batch) => $batch->whereRaw("batch_no LIKE ? ESCAPE '!'", [$pattern]));
            });
        }

        $items = AdminTableSort::apply($request, $items, [
            'pharmacy' => \Illuminate\Support\Facades\DB::raw('(SELECT name_en FROM pharmacies WHERE pharmacies.id = inventory_items.pharmacy_id)'),
            'medicine' => \Illuminate\Support\Facades\DB::raw('(SELECT name_en FROM medicines WHERE medicines.id = inventory_items.medicine_id)'),
            'price' => 'selling_price', 'listing' => 'is_listed', 'available' => 'available_quantity', 'batches' => 'batches_count',
        ]);

        return InventoryItemResource::collection($items->orderBy('pharmacy_id')->orderBy('id')->paginate(10)->withQueryString());
    }

    public function store(SaveInventoryListingRequest $request): JsonResponse
    {
        try {
            $item = InventoryItem::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            abort(409, 'This pharmacy already has a listing for that medicine.');
        }

        return $this->show($item)->response()->setStatusCode(201);
    }

    public function update(SaveInventoryListingRequest $request, InventoryItem $inventoryItem): InventoryItemResource
    {
        $inventoryItem->update($request->safe()->only(['selling_price', 'is_listed']));

        return $this->show($inventoryItem);
    }

    public function destroy(InventoryItem $inventoryItem): Response
    {
        try {
            $inventoryItem->delete();
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1451) {
                throw $exception;
            }

            abort(409, 'This listing has stock or reservation history. You can unlist it instead.');
        }

        return response()->noContent();
    }

    public function show(InventoryItem $inventoryItem): InventoryItemResource
    {
        $date = today('Asia/Amman')->toDateString();
        $inventoryItem->load([
            'pharmacy',
            'medicine',
            'batches' => fn ($query) => $query->orderBy('expiry_date')->orderBy('id'),
            'movements' => fn ($query) => $query->with('batch:id,batch_no')
                ->orderByDesc('inventory_movements.id')->limit(10),
        ]);

        foreach ($inventoryItem->batches as $batch) {
            $batch->setAttribute('available_quantity', $batch->availableQuantityOn($date));
        }

        $inventoryItem->setAttribute('available_quantity', $inventoryItem->batches->sum('available_quantity'));
        $inventoryItem->setAttribute('batches_count', $inventoryItem->batches->count());

        return InventoryItemResource::make($inventoryItem);
    }
}
