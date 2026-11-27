<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\MedicineResource;
use App\Models\Medicine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MedicineController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:150']]);
        $medicines = Medicine::query();

        if (isset($filters['search'])) {
            $pattern = '%'.strtr($filters['search'], ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $medicines->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("search_normalized LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("name_en LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("generic_name_en LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("generic_name_ar LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("barcode LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("jfda_registration_no LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('ingredients.activeIngredient', function (Builder $ingredient) use ($pattern): void {
                        $ingredient->whereRaw("name_en LIKE ? ESCAPE '!'", [$pattern])
                            ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern]);
                    });
            });
        }

        return MedicineResource::collection($medicines->orderBy('name_en')->orderBy('id')->paginate(10)->withQueryString());
    }

    public function show(Medicine $medicine): MedicineResource
    {
        return MedicineResource::make($medicine->load([
            'ingredients' => fn ($query) => $query->orderBy('id'),
            'ingredients.activeIngredient',
        ]));
    }
}
