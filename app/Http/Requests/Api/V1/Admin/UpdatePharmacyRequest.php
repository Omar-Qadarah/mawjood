<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PharmacyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePharmacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $pharmacy = $this->route('pharmacy');

        return [
            'name_ar' => ['sometimes', 'required', 'string', 'max:150'],
            'name_en' => ['sometimes', 'required', 'string', 'max:150'],
            'license_no' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('pharmacies', 'license_no')->ignore($pharmacy),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:29,33.5'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:34.9,39.4'],
            'opening_hours' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', Rule::enum(PharmacyStatus::class)],
        ];
    }
}
