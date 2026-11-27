<?php

namespace App\Http\Requests\Api\V1\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pharmacy_id' => ['required', 'integer', 'min:1'],
            'prescription_id' => ['nullable', 'integer', 'min:1'],
            'customer_note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._:-]+$/D'],
            'items' => ['required', 'array', 'list', 'min:1'],
            'items.*' => ['required', 'array:inventory_item_id,quantity'],
            'items.*.inventory_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'between:1,4294967295'],
        ];
    }
}
