<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PlatformRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');
        abort_if($customer && $customer->platform_role !== PlatformRole::Customer, 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $required = $customer ? ['sometimes', 'required'] : ['required'];
        $email = Rule::unique('users', 'email');
        if ($customer) {
            $email->ignore($customer);
        }

        return [
            'name' => [...$required, 'string', 'max:150'],
            'email' => [...$required, 'email', 'max:190', $email],
            'password' => [...$required, 'string', Password::min(10)->letters()->numbers()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
