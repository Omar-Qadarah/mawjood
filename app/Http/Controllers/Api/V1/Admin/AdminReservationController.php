<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Reservation\CancelReservationAction;
use App\Actions\Reservation\CompleteReservationAction;
use App\Actions\Reservation\ReviewReservationAction;
use App\Enums\BatchAllocationStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CompleteReservationRequest;
use App\Http\Requests\Api\V1\Admin\ReviewReservationRequest;
use App\Http\Resources\Api\V1\Admin\ReservationResource;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminReservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'governorate' => ['nullable', Rule::in(array_keys(config('jordan')))],
            'search' => ['nullable', 'string', 'max:150'],
            'deadline' => ['nullable', Rule::in(['upcoming'])],
        ]);

        $reservations = Reservation::query()
            ->with('pharmacy:id,name_en')
            ->withSum(['allocations as held_quantity' => fn (Builder $query) => $query
                ->where('batch_allocations.status', BatchAllocationStatus::Held->value)], 'quantity');

        if (isset($filters['status'])) {
            $reservations->where('status', $filters['status']);
        }

        if (isset($filters['governorate'])) {
            $reservations->whereHas('pharmacy', fn (Builder $query) => $query->where('governorate', $filters['governorate']));
        }

        if (isset($filters['deadline'])) {
            $reservations->nearingDeadline()
                ->orderByRaw('CASE WHEN status = ? THEN response_deadline ELSE pickup_deadline END',
                    [ReservationStatus::Pending->value]);
        }

        if (isset($filters['search'])) {
            $pattern = '%'.strtr($filters['search'], ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $reservations->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("code LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('pharmacy', fn (Builder $pharmacy) => $pharmacy
                        ->whereRaw("name_en LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("name_ar LIKE ? ESCAPE '!'", [$pattern]));
            });
        }

        return ReservationResource::collection($reservations->latest('id')->paginate(10)->withQueryString());
    }

    public function update(
        ReviewReservationRequest $request,
        Reservation $reservation,
        ReviewReservationAction $review,
    ): ReservationResource {
        $reservation = $review->execute(
            $reservation,
            $request->user(),
            ReservationStatus::from($request->validated('status')),
            $request->validated('rejection_reason'),
        );

        abort_if($reservation->status === ReservationStatus::Pending, 409, 'There is not enough eligible stock to confirm this reservation.');

        return $this->show($reservation);
    }

    public function complete(
        CompleteReservationRequest $request,
        Reservation $reservation,
        CompleteReservationAction $action,
    ): ReservationResource {
        return $this->show($action->execute($reservation, $request->user(), $request->validated('pickup_code')));
    }

    public function cancel(Request $request, Reservation $reservation, CancelReservationAction $action): ReservationResource
    {
        return $this->show($action->execute($reservation, $request->user()));
    }

    public function show(Reservation $reservation): ReservationResource
    {
        $reservation->load([
            'pharmacy:id,name_en',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.allocations' => fn ($query) => $query->orderBy('id'),
            'items.allocations.batch:id,batch_no',
        ]);

        $heldQuantity = $reservation->items->sum(fn ($item) => $item->allocations
            ->whereStrict('status', BatchAllocationStatus::Held)->sum('quantity'));
        $reservation->setAttribute('held_quantity', $heldQuantity);

        return ReservationResource::make($reservation);
    }
}
