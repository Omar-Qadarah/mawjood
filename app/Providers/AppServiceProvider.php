<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // User registration: 6 requests per minute per IP.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(6)
                ->by($request->ip());
        });

        // Login: 5 requests per minute per email and IP.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)
                ->by($email . '|' . $request->ip());
        });

        // Pharmacy registration: 3 requests per minute per IP.
        RateLimiter::for('pharmacy-register', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->ip());
        });
    }
}
