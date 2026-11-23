<?php

use App\Http\Controllers\Api\V1\Auth\RegisterController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;


Route::prefix('v1')->group(function () {
    
    Route::post('auth/register', RegisterController::class)
        ->middleware('throttle:register');

    Route::post('auth/login', LoginController::class)
        ->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('auth/me', MeController::class);

        Route::post('auth/logout', LogoutController::class);

    });

});
