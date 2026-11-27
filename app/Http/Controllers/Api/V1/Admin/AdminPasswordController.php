<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminPasswordController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('The new password must be no longer than 72 bytes.');
                    }
                }],
        ]);

        $user = DB::transaction(function () use ($request, $data): User {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if (! $user->password || ! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }

            $user->password = $data['password'];
            $user->setRememberToken(null);
            $user->save();

            return $user;
        }, attempts: 3);

        if ($request->hasSession()) {
            $request->user()->setRawAttributes($user->getAttributes(), true);
            $request->session()->regenerate(true);
        }

        return response()->noContent();
    }
}
