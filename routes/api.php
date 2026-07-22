<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SuperAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::get('me', [AuthController::class, 'me']);
});


Route::middleware(['role:superadmin', 'active'])->prefix('super-admin')->group(function () {
    Route::get('dashboard', [SuperAdminController::class, 'dashboardStats']);
    Route::prefix('front-office')->group(function () {
        Route::get('/', [SuperAdminController::class, 'index']);
        Route::post('/', [SuperAdminController::class, 'store']);
        Route::post('/{id}', [SuperAdminController::class, 'update']);
        Route::delete('/{id}', [SuperAdminController::class, 'destroy']);
    });
});
