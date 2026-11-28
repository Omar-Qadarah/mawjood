<?php

namespace App\Http\Requests\Auth;

use App\Rules\AccountPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? Str::lower(trim($this->email)) : $this->email,
            'phone' => is_string($this->phone) ? preg_replace('/[\s\-]/', '', $this->phone) : $this->phone,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/', 'max:20', 'unique:users,phone'],
            'password' => AccountPassword::rules(),
            'locale' => ['sometimes', 'in:ar,en'],
        ];
    }
}