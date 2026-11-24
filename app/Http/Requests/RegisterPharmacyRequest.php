<?php

namespace App\Http\Requests\Pharmacy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterPharmacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'license_no' => is_string($this->license_no)
                ? trim($this->license_no)
                : $this->license_no,

            'email' => is_string($this->email)
                ? Str::lower(trim($this->email))
                : $this->email,

            'phone' => is_string($this->phone)
                ? preg_replace('/[\s\-]/', '', $this->phone)
                : $this->phone,
        ]);
    }

    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],

            'license_no' => [
                'required',
                'string',
                'max:100',
                'unique:pharmacies,license_no',
            ],

            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:190'],

            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],

            'latitude' => [
                'required',
                'numeric',
                'between:29.0,33.5',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:34.9,39.4',
            ],

            'opening_hours' => ['sometimes', 'nullable', 'array'],
        ];
    }
}