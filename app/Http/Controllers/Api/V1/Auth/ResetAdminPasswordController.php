<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\AccountPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetAdminPasswordController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'token' => ['required', 'string', 'max:255'],
            'password' => AccountPassword::adminRules(),
        ]);
        $account = [
            'email' => Str::lower($data['email']),
            'platform_role' => PlatformRole::Admin->value,
            'is_active' => true,
        ];

        $user = DB::transaction(function () use ($account, $data): ?User {
            $user = User::query()->where($account)->lockForUpdate()->first();
            $status = Password::reset($account + $data, static function (User $user, string $password): void {
                $user->password = $password;
                $user->setRememberToken(null);
                $user->save();

                if (config('session.driver') === 'database') {
                    DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $user->id)->delete();
                }

                DB::afterCommit(static fn () => event(new PasswordReset($user)));
            });

            return $status === Password::PASSWORD_RESET ? $user : null;
        }, attempts: 3);

        if (! $user) {
            throw ValidationException::withMessages(['token' => 'This reset link is invalid or expired. Request a new link.']);
        }

        if ($request->hasSession() && Auth::guard('web')->id() === $user->id) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
