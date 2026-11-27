<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PharmacyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class PharmacyDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('phone'))) {
            return;
        }

        $phone = preg_replace('/[\s().-]+/u', '', $this->input('phone'));
        if (str_starts_with($phone, '00962')) {
            $phone = '+962'.substr($phone, 5);
        } elseif (str_starts_with($phone, '0')) {
            $phone = '+962'.substr($phone, 1);
        } elseif (str_starts_with($phone, '962')) {
            $phone = '+'.$phone;
        }

        $this->merge(['phone' => $phone === '' ? null : $phone]);
    }

    public function rules(): array
    {
        $pharmacy = $this->route('pharmacy');
        $required = $pharmacy ? ['sometimes', 'required'] : ['required'];
        $license = Rule::unique('pharmacies', 'license_no');
        if ($pharmacy) {
            $license->ignore($pharmacy);
        }

        return [
            'name_ar' => [...$required, 'string', 'max:150'],
            'name_en' => [...$required, 'string', 'max:150'],
            'license_no' => [...$required, 'string', 'max:100', $license],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+962(?:7[0-9]{8}|[2356][0-9]{7})$/D'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'address' => [...$required, 'string', 'max:255'],
            'governorate' => ['sometimes', 'required', 'string', Rule::in(array_keys(config('jordan')))],
            'city' => [...$required, 'string', 'max:100'],
            'latitude' => [...$required, 'numeric', 'between:29,33.5'],
            'longitude' => [...$required, 'numeric', 'between:34.9,39.4'],
            'opening_hours' => ['sometimes', 'nullable', 'array', 'list', 'max:100'],
            'opening_hours.*' => ['required', 'array:date,opens_at,closes_at'],
            'opening_hours.*.date' => ['required', 'date_format:Y-m-d'],
            'opening_hours.*.opens_at' => ['required', 'date_format:H:i'],
            'opening_hours.*.closes_at' => ['required', 'date_format:H:i', 'after:opening_hours.*.opens_at'],
            'status' => ['sometimes', Rule::enum(PharmacyStatus::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $pharmacy = $this->route('pharmacy');
                if ($this->hasAny(['governorate', 'city'])) {
                    $governorate = $this->input('governorate', $pharmacy?->governorate);
                    $city = $this->input('city', $pharmacy?->city);
                    $places = config('jordan');
                    if ($governorate !== null && ! in_array($city, $places[$governorate]['cities'], true)) {
                        $validator->errors()->add('city', 'Choose a city in the selected governorate.');
                    }
                }

                $byDate = [];
                foreach ($this->input('opening_hours') ?? [] as $slot) {
                    $byDate[$slot['date']][] = $slot;
                }
                foreach ($byDate as $slots) {
                    usort($slots, fn (array $first, array $second) => $first['opens_at'] <=> $second['opens_at']);
                    $lastClose = '';
                    foreach ($slots as $slot) {
                        if ($slot['opens_at'] < $lastClose) {
                            $validator->errors()->add('opening_hours', 'Opening time slots on the same date cannot overlap.');

                            return;
                        }
                        $lastClose = $slot['closes_at'];
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter a Jordanian mobile or landline number, such as 079 123 4567 or 06 123 4567.'];
    }
}
