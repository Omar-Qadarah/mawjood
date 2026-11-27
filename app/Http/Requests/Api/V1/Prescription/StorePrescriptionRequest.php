<?php

namespace App\Http\Requests\Api\V1\Prescription;

use App\Enums\PlatformRole;
use Illuminate\Foundation\Http\FormRequest;

class StorePrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_active && $this->user()->platform_role === PlatformRole::Customer;
    }

    public function rules(): array
    {
        return ['image' => ['bail', 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1,min_height=1']];
    }
}
