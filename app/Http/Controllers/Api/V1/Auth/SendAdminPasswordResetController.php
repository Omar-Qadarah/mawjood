<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminPasswordReset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendAdminPasswordResetController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);
        $credentials = [
            'email' => Str::lower($data['email']),
            'platform_role' => PlatformRole::Admin->value,
            'is_active' => true,
        ];

        DB::transaction(function () use ($credentials): void {
            User::query()->where($credentials)->lockForUpdate()->first();

            Password::sendResetLink($credentials, static function (User $user, string $token): void {
                DB::afterCommit(static function () use ($user, $token): void {
                    try {
                        $user->notify(new AdminPasswordReset($token));
                    } catch (InvalidArgumentException|TransportExceptionInterface $exception) {
                        Log::warning('Admin password reset email delivery failed.', ['transport_error' => $exception::class]);
                    }
                });
            });
        }, attempts: 3);

        return response()->noContent();
    }
}
