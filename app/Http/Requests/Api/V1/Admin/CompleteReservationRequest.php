<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CompleteReservationRequest extends FormRequest
{
    public function rules(): array
    {
        return ['pickup_code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']];
    }
}
