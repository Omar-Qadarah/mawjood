<?php

use App\Http\Controllers\Api\V1\Admin\ActiveIngredientController;
use App\Http\Controllers\Api\V1\Admin\AdminPasswordController;
use App\Http\Controllers\Api\V1\Admin\AdminPharmacyController;
use App\Http\Controllers\Api\V1\Admin\AdminProfileController;
use App\Http\Controllers\Api\V1\Admin\AdminReservationController;
use App\Http\Controllers\Api\V1\Admin\CustomerController;
use App\Http\Controllers\Api\V1\Admin\DashboardSummaryController;
use App\Http\Controllers\Api\V1\Admin\ExpiringInventoryBatchController;
use App\Http\Controllers\Api\V1\Admin\InventoryController;
use App\Http\Controllers\Api\V1\Admin\MedicineController;
use App\Http\Controllers\Api\V1\Admin\PharmacyMapController;
use App\Http\Controllers\Api\V1\Admin\StockMovementController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Medicine\MedicineSearchController;
use App\Http\Controllers\Api\V1\Medicine\SmartMedicineSearchController;
use App\Http\Controllers\Api\V1\Pharmacy\PharmacyController;
use App\Http\Controllers\Api\V1\Prescription\PrescriptionController;
use App\Http\Controllers\Api\V1\Reservation\ReservationController;
use Illuminate\Support\Facades\Route;

Route::pattern('medicine', '[0-9]+');
Route::pattern('inventoryItem', '[0-9]+');
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

        Route::post('medicines/smart-search', SmartMedicineSearchController::class)
            ->middleware('throttle:smart-search')->name('medicines.smart-search');

        // Administrator routes
        Route::middleware(['auth:sanctum', 'admin'])
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {
                Route::post('profile', [AdminProfileController::class, 'update'])->name('profile.update');
                Route::get('profile/avatar', [AdminProfileController::class, 'avatar'])->name('profile.avatar');
                Route::patch('profile/password', AdminPasswordController::class)
                    ->middleware('throttle:10,1')->name('profile.password');
                Route::get('pharmacy-sites', PharmacyMapController::class)->name('pharmacy-sites');
                Route::apiResource('pharmacies', AdminPharmacyController::class);
                Route::apiResource('customers', CustomerController::class);
                Route::apiResource('medicines', MedicineController::class);
                Route::apiResource('active-ingredients', ActiveIngredientController::class)
                    ->parameters(['active-ingredients' => 'activeIngredient']);
                Route::get('inventory-batches/expiring', ExpiringInventoryBatchController::class)->name('inventory-batches.expiring');
                Route::apiResource('inventory-items', InventoryController::class)
                    ->parameters(['inventory-items' => 'inventoryItem']);
                Route::post('inventory-items/{inventoryItem}/receipts', [StockMovementController::class, 'receive'])
                    ->whereNumber('inventoryItem')->name('inventory-items.receipts.store');
                Route::post('inventory-items/{inventoryItem}/batches/{batch}/adjustments', [StockMovementController::class, 'adjust'])
                    ->whereNumber(['inventoryItem', 'batch'])->name('inventory-items.adjustments.store');
                Route::post('reservations/{reservation}/complete', [AdminReservationController::class, 'complete'])
                    ->whereNumber('reservation')->middleware('throttle:10,1')->name('reservations.complete');
                Route::post('reservations/{reservation}/cancel', [AdminReservationController::class, 'cancel'])
                    ->whereNumber('reservation')->name('reservations.cancel');
                Route::apiResource('reservations', AdminReservationController::class)->only(['index', 'show', 'update']);
                Route::get('dashboard/summary', DashboardSummaryController::class)
                    ->name('dashboard.summary');
            });
        // Authenticated routes
        Route::middleware('auth:sanctum')->group(function () {

            Route::get('auth/me', MeController::class)
                ->name('auth.me');

            Route::post('auth/logout', LogoutController::class)
                ->name('auth.logout');

            Route::post('reservations/{reservation}/pickup-code', [ReservationController::class, 'pickupCode'])
                ->whereNumber('reservation')->middleware('throttle:10,1')->name('reservations.pickup-code');
            Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');

            Route::post('prescriptions', [PrescriptionController::class, 'store'])
                ->middleware('throttle:10,1')->name('prescriptions.store');
            Route::get('reservations/{reservation}/prescription/image', [PrescriptionController::class, 'image'])
                ->whereNumber('reservation')->name('reservations.prescription.image');

            // Pharmacy
            Route::post('pharmacies', [PharmacyController::class, 'store'])
                ->middleware('throttle:pharmacy-register')
                ->name('pharmacies.store');

        });

    });
