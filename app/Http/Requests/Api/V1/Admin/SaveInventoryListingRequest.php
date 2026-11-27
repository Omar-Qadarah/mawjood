<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveInventoryListingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'pharmacy_id' => $this->isMethod('POST') ? ['required', 'integer', 'exists:pharmacies,id'] : ['prohibited'],
            'medicine_id' => $this->isMethod('POST') ? ['required', 'integer', 'exists:medicines,id'] : ['prohibited'],
            'selling_price' => ['required', 'numeric', 'decimal:0,3', 'between:0,9999999.999'],
            'is_listed' => ['required', 'boolean'],
        ];
    }
}
