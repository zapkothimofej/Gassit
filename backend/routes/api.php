<?php

use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ParkController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/2fa/setup', [TwoFactorController::class, 'setup']);
        Route::post('/2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable']);
    });
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('parks')->group(function () {
    Route::get('/', [ParkController::class, 'index']);
    Route::post('/', [ParkController::class, 'store']);
    Route::put('/{id}', [ParkController::class, 'update']);
    Route::delete('/{id}', [ParkController::class, 'destroy']);
    Route::post('/{id}/logo', [ParkController::class, 'uploadLogo']);
    Route::get('/{id}/settings', [ParkController::class, 'getSettings']);
    Route::put('/{id}/settings', [ParkController::class, 'updateSettings']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('parks/{parkId}/unit-types')->group(function () {
    Route::get('/', [UnitTypeController::class, 'index']);
    Route::post('/', [UnitTypeController::class, 'store']);
    Route::put('/{id}', [UnitTypeController::class, 'update']);
    Route::delete('/{id}', [UnitTypeController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('unit-types')->group(function () {
    Route::post('/{id}/floor-plan', [UnitTypeController::class, 'uploadFloorPlan']);
    Route::post('/{id}/features', [UnitTypeController::class, 'syncFeatures']);
    Route::get('/{id}/availability', [UnitTypeController::class, 'availability']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager'])->prefix('parks/{parkId}/units')->group(function () {
    Route::get('/', [UnitController::class, 'index']);
    Route::post('/', [UnitController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager'])->prefix('units')->group(function () {
    Route::put('/{id}', [UnitController::class, 'update']);
    Route::delete('/{id}', [UnitController::class, 'destroy']);
    Route::put('/{id}/status', [UnitController::class, 'updateStatus']);
    Route::post('/{id}/photos', [UnitController::class, 'uploadPhoto']);
    Route::delete('/{id}/photos/{photoId}', [UnitController::class, 'deletePhoto']);
    Route::get('/{id}/history', [UnitController::class, 'history']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::post('/users/{id}/parks', [UserController::class, 'syncParks']);

    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
});
