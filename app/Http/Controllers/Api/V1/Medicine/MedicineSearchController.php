<?php

namespace App\Http\Controllers\Api\V1\Medicine;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Medicine\MedicineSearchResource;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MedicineSearchController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['search' => ['required', 'string', 'max:150']]);
        $date = today('Asia/Amman')->toDateString();

        $results = InventoryItem::query()
            ->with(['medicine', 'pharmacy'])
            ->where('is_listed', true)
            ->whereHas('pharmacy', fn (Builder $query) => $query->searchable())
            ->whereHas('medicine', fn (Builder $query) => $query->matchingCatalogSearch($filters['search']))
            ->whereHas('batches', fn (Builder $query) => $query->eligibleOn($date)
                ->whereColumn('quantity_on_hand', '>', 'quantity_held'))
            ->withAvailableQuantity($date)
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return MedicineSearchResource::collection($results);
    }
}
