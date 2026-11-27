<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Rules\LocalizedName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveActiveIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $name = Rule::unique('active_ingredients', 'name_en');
        if ($ingredient = $this->route('activeIngredient')) {
            $name->ignore($ingredient);
        }

        $required = $this->isMethod('PATCH') ? ['sometimes', 'required'] : ['required'];

        return [
            'name_en' => [...$required, 'string', 'max:150', $name, LocalizedName::CATALOG_ENGLISH],
            'name_ar' => ['nullable', 'string', 'max:150', LocalizedName::CATALOG_ARABIC],
            'atc_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name_en.regex' => 'Enter the English ingredient name using English letters.',
            'name_ar.regex' => 'Enter the Arabic ingredient name using Arabic letters.',
        ];
    }
}
