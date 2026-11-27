<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\InventoryItemResource;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'pharmacy_id' => ['nullable', 'integer', 'min:1', 'exists:pharmacies,id'],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $date = today('Asia/Amman')->toDateString();
        $items = InventoryItem::query()
            ->with(['pharmacy', 'medicine'])
            ->withCount('batches')
            ->withSum(
                ['batches as available_quantity' => fn (Builder $query) => $query->eligibleOn($date)],
                DB::raw('quantity_on_hand - quantity_held'),
            );

        if (isset($filters['pharmacy_id'])) {
            $items->where('pharmacy_id', $filters['pharmacy_id']);
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

        return InventoryItemResource::collection($items->orderBy('pharmacy_id')->orderBy('id')->paginate(10)->withQueryString());
    }

    public function show(InventoryItem $inventoryItem): InventoryItemResource
    {
        $date = today('Asia/Amman')->toDateString();
        $inventoryItem->load(['pharmacy', 'medicine', 'batches' => fn ($query) => $query->orderBy('expiry_date')->orderBy('id')]);

        foreach ($inventoryItem->batches as $batch) {
            $batch->setAttribute('available_quantity', $batch->availableQuantityOn($date));
        }

        $inventoryItem->setAttribute('available_quantity', $inventoryItem->batches->sum('available_quantity'));
        $inventoryItem->setAttribute('batches_count', $inventoryItem->batches->count());

        return InventoryItemResource::make($inventoryItem);
    }
}
