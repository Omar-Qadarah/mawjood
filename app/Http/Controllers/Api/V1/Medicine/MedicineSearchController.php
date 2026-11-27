<?php

namespace App\Http\Controllers\Api\V1\Medicine;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Medicine\MedicineSearchResource;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MedicineSearchController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['search' => ['required', 'string', 'max:150']]);
        $date = today('Asia/Amman')->toDateString();

        $results = InventoryItem::query()->publicMedicineSearch($filters['search'], $date)
            ->paginate(10)
            ->withQueryString();

        return MedicineSearchResource::collection($results);
    }
}
