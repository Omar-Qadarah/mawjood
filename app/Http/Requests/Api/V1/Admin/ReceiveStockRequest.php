<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_no' => ['required', 'string', 'max:60'],
            'expiry_date' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'reason' => ['nullable', 'string', 'max:255'],
            'request_key' => ['nullable', 'uuid'],
        ];
    }
}
