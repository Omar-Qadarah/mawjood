<?php

namespace App\Http\Middleware;

use App\Enums\PlatformRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user && $user->is_active && $user->platform_role === PlatformRole::Admin,
            403,
            'Administrator access is required.'
        );

        return $next($request);
    }
}
