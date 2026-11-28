<?php

namespace App\Rules;

use Closure;
use Illuminate\Validation\Rules\Password;

class AccountPassword
{
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            Password::min(8)->mixedCase()->numbers(),
            static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! preg_match('/[\p{P}\p{S}]/u', $value)) {
                    $fail('The password must contain at least one symbol.');
                }
            },
        ];
    }
}
