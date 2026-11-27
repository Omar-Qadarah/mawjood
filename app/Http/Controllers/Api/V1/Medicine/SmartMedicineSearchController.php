<?php

namespace App\Http\Controllers\Api\V1\Medicine;

use App\Actions\Medicine\InterpretMedicineSearchAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Medicine\MedicineSearchResource;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SmartMedicineSearchController extends Controller
{
    public function __invoke(Request $request, InterpretMedicineSearchAction $interpret): AnonymousResourceCollection
    {
        $model = config('services.openai.smart_search_model');
        $key = config('services.openai.key');
        $started = hrtime(true);
        $metadata = ['status' => 'rejected', 'error_code' => 'invalid_input'];
        try {
            $data = Validator::make($request->all(), [
                'search' => ['required', 'string', 'max:150'],
                'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            ])->validate();

            if (! $key) {
                $metadata['error_code'] = 'provider_not_configured';
                abort(503, 'Smart search is unavailable. Use the standard medicine search.');
            }

            try {
                $match = $interpret->execute($data['search'], $model, $key);
            } catch (Throwable) {
                // Provider exceptions can contain response content. Record only a fixed error code.
                $metadata = ['status' => 'failed', 'error_code' => 'provider_error'];
                abort(502, 'Smart search is unavailable. Use the standard medicine search.');
            }

            $metadata = [
                'status' => $match['term'] === null ? 'rejected' : 'ok',
                'error_code' => $match['term'] === null ? 'no_medicine_named' : null,
                'input_tokens' => $match['input_tokens'],
                'output_tokens' => $match['output_tokens'],
            ];
            if ($match['term'] === null) {
                abort(422, 'Enter a medicine or active ingredient name.');
            }

            $results = InventoryItem::query()->publicMedicineSearch($match['term'], today('Asia/Amman')->toDateString())
                ->paginate(10, ['*'], 'page', $data['page'] ?? 1);

            return MedicineSearchResource::collection($results);
        } finally {
            DB::table('ai_requests')->insert($metadata + [
                'user_id' => $request->user('sanctum')?->id,
                'feature' => 'smart_search',
                'model' => $model,
                'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'created_at' => now(),
            ]);
        }
    }
}
