<?php

namespace App\Http\Requests\Api\V1\Pharmacy;

use App\Rules\LocalizedName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePharmacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication is enforced by auth:sanctum on the route.
        return true;
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:150', LocalizedName::ARABIC],

            'name_en' => ['required', 'string', 'max:150', LocalizedName::ENGLISH],

            'license_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('pharmacies', 'license_no'),
            ],

            'phone' => ['nullable', 'string', 'max:20'],

            'email' => ['nullable', 'email', 'max:190'],

            'address' => ['required', 'string', 'max:255'],

            'city' => ['required', 'string', 'max:100'],

            // Approximate Jordan geographic bounds.
            'latitude' => ['required', 'numeric', 'between:29,33.5'],

            'longitude' => ['required', 'numeric', 'between:34.9,39.4'],

            'opening_hours' => ['nullable', 'array'],
        ];
    }
    public function messages(): array
    {
        return [
            'name_ar.regex' => 'Enter the Arabic name using Arabic letters.',
            'name_en.regex' => 'Enter the English name using English letters.',
        ];
    }
}
