<?php

use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DiscountRuleController;
use App\Http\Controllers\InsuranceOptionController;
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

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('parks/{parkId}/discount-rules')->group(function () {
    Route::get('/', [DiscountRuleController::class, 'index']);
    Route::post('/', [DiscountRuleController::class, 'store']);
    Route::put('/{id}', [DiscountRuleController::class, 'update']);
    Route::delete('/{id}', [DiscountRuleController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('parks/{parkId}/insurance-options')->group(function () {
    Route::get('/', [InsuranceOptionController::class, 'index']);
    Route::post('/', [InsuranceOptionController::class, 'store']);
    Route::put('/{id}', [InsuranceOptionController::class, 'update']);
    Route::delete('/{id}', [InsuranceOptionController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('unit-types/{id}')->group(function () {
    Route::get('/discount-rules', [DiscountRuleController::class, 'forUnitType']);
    Route::get('/insurance-options', [InsuranceOptionController::class, 'forUnitType']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,office_worker'])->prefix('customers')->group(function () {
    Route::get('/blacklist', [CustomerController::class, 'blacklistIndex']);
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::put('/{id}', [CustomerController::class, 'update']);
    Route::delete('/{id}', [CustomerController::class, 'destroy']);
    Route::post('/{id}/documents', [CustomerController::class, 'uploadDocument']);
    Route::get('/{id}/documents', [CustomerController::class, 'listDocuments']);
    Route::delete('/{id}/documents/{docId}', [CustomerController::class, 'deleteDocument']);
    Route::post('/{id}/gdpr-delete', [CustomerController::class, 'gdprDelete']);
    Route::post('/{id}/blacklist', [CustomerController::class, 'blacklist']);
    Route::delete('/{id}/blacklist', [CustomerController::class, 'removeBlacklist']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,office_worker'])->prefix('applications')->group(function () {
    Route::get('/', [ApplicationController::class, 'index']);
    Route::post('/', [ApplicationController::class, 'store']);
    Route::put('/{id}', [ApplicationController::class, 'update']);
    Route::delete('/{id}', [ApplicationController::class, 'destroy']);
    Route::put('/{id}/status', [ApplicationController::class, 'updateStatus']);
    Route::post('/{id}/assign', [ApplicationController::class, 'assign']);
    Route::post('/{id}/credit-check', [ApplicationController::class, 'creditCheck']);
    Route::post('/{id}/waiting-list', [ApplicationController::class, 'moveToWaitingList']);
    Route::post('/{id}/convert', [ApplicationController::class, 'convert']);
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
