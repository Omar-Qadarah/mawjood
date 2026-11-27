<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'not_in:0', 'between:-2147483648,2147483647'],
            'reason' => ['required', 'string', 'max:255'],
            'request_key' => ['nullable', 'uuid'],
        ];
    }
}
