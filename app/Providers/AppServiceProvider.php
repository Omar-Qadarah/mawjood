<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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
        RateLimiter::for('smart-search', function (Request $request) {
            $userId = $request->user('sanctum')?->id;

            return Limit::perMinute(10)->by($userId ?? $request->ip())
                ->response(function (Request $request, array $headers) use ($userId) {
                    DB::table('ai_requests')->insert([
                        'user_id' => $userId,
                        'feature' => 'smart_search',
                        'model' => config('services.openai.smart_search_model'),
                        'status' => 'rejected',
                        'error_code' => 'rate_limited',
                        'created_at' => now(),
                    ]);

                    return response()->json(['message' => 'Too many smart search requests.'], 429, $headers);
                });
        });

        // User registration: 6 requests per minute per IP.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(6)
                ->by($request->ip());
        });

        // Login: 5 requests per minute per email and IP.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)
                ->by($email.'|'.$request->ip());
        });

        // Pharmacy registration: 3 requests per minute per IP.
        RateLimiter::for('pharmacy-register', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->ip());
        });
    }
}
