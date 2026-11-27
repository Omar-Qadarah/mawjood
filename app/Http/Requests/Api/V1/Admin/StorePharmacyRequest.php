<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PharmacyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePharmacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'license_no' => ['required', 'string', 'max:100', Rule::unique('pharmacies', 'license_no')],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:29,33.5'],
            'longitude' => ['required', 'numeric', 'between:34.9,39.4'],
            'opening_hours' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', Rule::enum(PharmacyStatus::class)],
        ];
    }
}
