<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Medicine\SaveMedicineAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SaveMedicineRequest;
use App\Http\Resources\Api\V1\Admin\MedicineResource;
use App\Models\Medicine;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MedicineController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'catalog_status' => ['nullable', 'string', 'max:20'],
        ]);
        $medicines = Medicine::query();

        if (isset($filters['search'])) {
            $medicines->matchingCatalogSearch($filters['search']);
        }

        if (isset($filters['catalog_status'])) {
            $medicines->where('catalog_status', $filters['catalog_status']);
        }

        return MedicineResource::collection($medicines->orderBy('name_en')->orderBy('id')->paginate(10)->withQueryString())
            ->additional(['meta' => ['catalog_statuses' => Medicine::query()->distinct()->orderBy('catalog_status')->pluck('catalog_status')]]);
    }

    public function show(Medicine $medicine): MedicineResource
    {
        return MedicineResource::make($medicine->load([
            'ingredients' => fn ($query) => $query->orderBy('id'),
            'ingredients.activeIngredient',
        ]));
    }

    public function store(SaveMedicineRequest $request, SaveMedicineAction $action): JsonResponse
    {
        return $this->show($action->execute(new Medicine, $request->validated()))
            ->response()->setStatusCode(201);
    }

    public function update(SaveMedicineRequest $request, Medicine $medicine, SaveMedicineAction $action): MedicineResource
    {
        return $this->show($action->execute($medicine, $request->validated()));
    }

    public function destroy(Medicine $medicine): Response
    {
        try {
            DB::transaction(function () use ($medicine): void {
                $medicine = Medicine::query()->lockForUpdate()->findOrFail($medicine->getKey());
                abort_if($medicine->inventoryItems()->exists(), 409, 'This medicine package has pharmacy inventory. Its catalog record must be kept.');
                $medicine->ingredients()->delete();
                $medicine->delete();
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }

            abort(409, 'This medicine package has related stock records. Its catalog record must be kept.');
        }

        return response()->noContent();
    }
}
