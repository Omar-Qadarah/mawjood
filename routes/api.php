<?php

use App\Http\Controllers\Api\V1\Admin\AdminPharmacyController;
use App\Http\Controllers\Api\V1\Admin\CustomerController;
use App\Http\Controllers\Api\V1\Admin\DashboardSummaryController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Pharmacy\PharmacyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function () {

        // Public authentication routes
        Route::post('auth/register', RegisterController::class)
            ->middleware('throttle:register')
            ->name('auth.register');

        Route::post('auth/login', LoginController::class)
            ->middleware('throttle:login')
            ->name('auth.login');

        // Administrator routes
        Route::middleware(['auth:sanctum', 'admin'])
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {
                Route::apiResource('pharmacies', AdminPharmacyController::class);
                Route::apiResource('customers', CustomerController::class);
                Route::get('dashboard/summary', DashboardSummaryController::class)
                    ->name('dashboard.summary');
            });
        // Authenticated routes
        Route::middleware('auth:sanctum')->group(function () {

            Route::get('auth/me', MeController::class)
                ->name('auth.me');

            Route::post('auth/logout', LogoutController::class)
                ->name('auth.logout');

            // Pharmacy
            Route::post('pharmacies', [PharmacyController::class, 'store'])
                ->middleware('throttle:pharmacy-register')
                ->name('pharmacies.store');

        });

    });
