<?php

use App\Http\Controllers\DatabaseMaintenanceController;
use App\Http\Controllers\ParkingStateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/status', [ParkingStateController::class, 'status']);
Route::get('/events', [ParkingStateController::class, 'events']);
Route::post('/spots/toggle', [ParkingStateController::class, 'toggleSpot']);
Route::post('/spots/toggle-service-reservation', [ParkingStateController::class, 'toggleServiceReservation']);

Route::middleware('admin.token')->group(function () {
    Route::post('/admin/init-or-reseed', [ParkingStateController::class, 'initOrReseed']);
    Route::post('/admin/set-free', [ParkingStateController::class, 'setFree']);

    Route::post('/admin/database-migrate', [DatabaseMaintenanceController::class, 'migrate']);
    Route::post('/admin/database-migrate-status', [DatabaseMaintenanceController::class, 'migrateStatus']);
    Route::post('/admin/database-migrate-rollback', [DatabaseMaintenanceController::class, 'migrateRollback']);
    Route::post('/admin/cache', [DatabaseMaintenanceController::class, 'clearCaches']);
});
