<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SaveCustomerRequest;
use App\Http\Resources\Api\V1\Admin\CustomerResource;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'name' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $customers = User::query()
            ->where('platform_role', PlatformRole::Customer->value)
            ->withCount('memberships')
            ->when(isset($filters['name']), function ($query) use ($filters): void {
                $pattern = '%'.strtr($filters['name'], ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]);
            })
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['status']), fn ($query) => $query->where('is_active', $filters['status'] === 'active'))
            ->latest('id')
            ->paginate(20);

        return CustomerResource::collection($customers);
    }

    public function store(SaveCustomerRequest $request): JsonResponse
    {
        $customer = new User($request->safe()->except('is_active'));
        $customer->is_active = $request->boolean('is_active', true);
        $customer->save();

        return CustomerResource::make($customer->refresh()->loadCount('memberships'))
            ->response()->setStatusCode(201);
    }

    public function show(User $customer): CustomerResource
    {
        $this->ensureCustomer($customer);

        return CustomerResource::make($customer->load('memberships.pharmacy'));
    }

    public function update(SaveCustomerRequest $request, User $customer): CustomerResource
    {
        $this->ensureCustomer($customer);
        if ($request->has('is_active')) {
            $customer->is_active = $request->boolean('is_active');
        }

        $customer->fill($request->safe()->except('is_active'))->save();

        return CustomerResource::make($customer->refresh()->loadCount('memberships'));
    }

    public function destroy(User $customer): Response
    {
        $this->ensureCustomer($customer);
        abort_if($customer->memberships()->exists(), 409, 'This customer has pharmacy memberships. Deactivate the account to preserve them.');

        try {
            $customer->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }

            abort(409, 'This customer has related records. Deactivate the account to preserve them.');
        }

        return response()->noContent();
    }

    private function ensureCustomer(User $customer): void
    {
        abort_unless($customer->platform_role === PlatformRole::Customer, 404);
    }
}
