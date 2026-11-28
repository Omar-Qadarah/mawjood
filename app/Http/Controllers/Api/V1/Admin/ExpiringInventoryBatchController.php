<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\ExpiringInventoryBatchResource;
use App\Models\InventoryBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpiringInventoryBatchController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'pharmacy_id' => ['nullable', 'integer', 'min:1', 'exists:pharmacies,id'],
            'listing' => ['nullable', 'in:listed,unlisted'],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $batches = InventoryBatch::query()->expiringSoon()
            ->with(['inventoryItem.pharmacy', 'inventoryItem.medicine']);

        if (isset($filters['pharmacy_id']) || isset($filters['listing'])) {
            $batches->whereHas('inventoryItem', function (Builder $query) use ($filters): void {
                if (isset($filters['pharmacy_id'])) {
                    $query->where('pharmacy_id', $filters['pharmacy_id']);
                }
                if (isset($filters['listing'])) {
                    $query->where('is_listed', $filters['listing'] === 'listed');
                }
            });
        }

        if (isset($filters['search'])) {
            $pattern = '%'.strtr($filters['search'], ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $batches->where(fn (Builder $query) => $query
                ->whereRaw("batch_no LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('inventoryItem.pharmacy', fn (Builder $pharmacy) => $pharmacy
                    ->whereRaw("name_en LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern]))
                ->orWhereHas('inventoryItem.medicine', fn (Builder $medicine) => $medicine
                    ->whereRaw("name_en LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern])));
        }

        return ExpiringInventoryBatchResource::collection(
            $batches->orderBy('expiry_date')->orderBy('id')->paginate(10)->withQueryString(),
        );
    }
}
