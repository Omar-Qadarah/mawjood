<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SaveActiveIngredientRequest;
use App\Http\Resources\Api\V1\Admin\ActiveIngredientResource;
use App\Models\ActiveIngredient;
use App\Support\AdminTableSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ActiveIngredientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'usage' => ['nullable', 'in:used,unused'],
        ]);
        $ingredients = ActiveIngredient::query()->withCount('medicineIngredients');

        if (isset($filters['search'])) {
            $pattern = '%'.strtr($filters['search'], ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $ingredients->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("name_en LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("atc_code LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        if (($filters['usage'] ?? null) === 'used') {
            $ingredients->has('medicineIngredients');
        } elseif (($filters['usage'] ?? null) === 'unused') {
            $ingredients->doesntHave('medicineIngredients');
        }

        $ingredients = AdminTableSort::apply($request, $ingredients, [
            'name' => 'name_en', 'atc' => 'atc_code', 'packages' => 'medicine_ingredients_count',
        ]);

        return ActiveIngredientResource::collection($ingredients->orderBy('name_en')->orderBy('id')->paginate(20)->withQueryString());
    }

    public function show(ActiveIngredient $activeIngredient): ActiveIngredientResource
    {
        return ActiveIngredientResource::make($activeIngredient->loadCount('medicineIngredients'));
    }

    public function store(SaveActiveIngredientRequest $request): JsonResponse
    {
        return ActiveIngredientResource::make(ActiveIngredient::create($request->validated()))
            ->response()->setStatusCode(201);
    }

    public function update(SaveActiveIngredientRequest $request, ActiveIngredient $activeIngredient): ActiveIngredientResource
    {
        $activeIngredient->update($request->validated());

        return $this->show($activeIngredient);
    }

    public function destroy(ActiveIngredient $activeIngredient): Response
    {
        try {
            $activeIngredient->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }

            abort(409, 'This ingredient is used by a medicine package. Remove it from each package before deleting it.');
        }

        return response()->noContent();
    }
}
