<?php

namespace App\Http\Requests\Api\V1\Admin;

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
            'name_en' => [...$required, 'string', 'max:150', $name],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'atc_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
