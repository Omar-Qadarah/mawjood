<?php

use App\Http\Controllers\Api\V1\Admin\ActiveIngredientController;
use App\Http\Controllers\Api\V1\Admin\AdminPharmacyController;
use App\Http\Controllers\Api\V1\Admin\CustomerController;
use App\Http\Controllers\Api\V1\Admin\DashboardSummaryController;
use App\Http\Controllers\Api\V1\Admin\InventoryController;
use App\Http\Controllers\Api\V1\Admin\MedicineController;
use App\Http\Controllers\Api\V1\Admin\PharmacyMapController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Medicine\MedicineSearchController;
use App\Http\Controllers\Api\V1\Pharmacy\PharmacyController;
use Illuminate\Support\Facades\Route;

Route::pattern('medicine', '[0-9]+');
Route::pattern('activeIngredient', '[0-9]+');

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

        Route::get('medicines/search', MedicineSearchController::class)->name('medicines.search');

        // Administrator routes
        Route::middleware(['auth:sanctum', 'admin'])
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {
                Route::get('pharmacy-sites', PharmacyMapController::class)->name('pharmacy-sites');
                Route::apiResource('pharmacies', AdminPharmacyController::class);
                Route::apiResource('customers', CustomerController::class);
                Route::apiResource('medicines', MedicineController::class);
                Route::apiResource('active-ingredients', ActiveIngredientController::class)
                    ->parameters(['active-ingredients' => 'activeIngredient']);
                Route::get('inventory-items', [InventoryController::class, 'index'])->name('inventory-items.index');
                Route::get('inventory-items/{inventoryItem}', [InventoryController::class, 'show'])
                    ->whereNumber('inventoryItem')->name('inventory-items.show');
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
