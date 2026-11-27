<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Rules\LocalizedName;
use Illuminate\Foundation\Http\FormRequest;

class SaveMedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? ['sometimes', 'required'] : ['required'];

        return [
            'name_en' => [...$required, 'string', 'max:200', LocalizedName::CATALOG_ENGLISH],
            'name_ar' => ['nullable', 'string', 'max:200', LocalizedName::CATALOG_ARABIC],
            'generic_name_en' => [...$required, 'string', 'max:200', LocalizedName::CATALOG_ENGLISH],
            'generic_name_ar' => ['nullable', 'string', 'max:200', LocalizedName::CATALOG_ARABIC],
            'dosage_form' => [...$required, 'string', 'max:40'],
            'route' => [...$required, 'string', 'max:40'],
            'brand_name' => ['nullable', 'string', 'max:150'],
            'manufacturer_name' => [...$required, 'string', 'max:150'],
            'pack_size' => [...$required, 'numeric', 'gt:0', 'decimal:0,3', 'max:9999999.999'],
            'pack_unit' => [...$required, 'string', 'max:30'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'jfda_registration_no' => ['nullable', 'string', 'max:100'],
            'registration_status' => ['nullable', 'string', 'max:30'],
            'registration_expiry' => ['nullable', 'date_format:Y-m-d'],
            'official_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,3', 'max:9999999.999'],
            'currency' => ['sometimes', 'required', 'regex:/^[A-Z]{3}$/D'],
            'official_price_effective_date' => ['nullable', 'date_format:Y-m-d'],
            'dispensing_class' => [...$required, 'string', 'max:30'],
            'catalog_status' => [...$required, 'string', 'max:20'],
            'ingredients' => [...$required, 'array', 'list', 'min:1'],
            'ingredients.*' => ['required', 'array:active_ingredient_id,strength_value,strength_unit,per_value,per_unit'],
            'ingredients.*.active_ingredient_id' => ['required', 'integer', 'distinct', 'exists:active_ingredients,id'],
            'ingredients.*.strength_value' => ['required', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999999.9999'],
            'ingredients.*.strength_unit' => ['required', 'string', 'max:10'],
            'ingredients.*.per_value' => ['nullable', 'required_with:ingredients.*.per_unit', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999999.9999'],
            'ingredients.*.per_unit' => ['nullable', 'required_with:ingredients.*.per_value', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'name_en.regex' => 'Enter the English product name using English letters.',
            'name_ar.regex' => 'Enter the Arabic product name using Arabic letters.',
            'generic_name_en.regex' => 'Enter the English generic name using English letters.',
            'generic_name_ar.regex' => 'Enter the Arabic generic name using Arabic letters.',
        ];
    }
}
