<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FrontOfficeController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SuperAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware(['active'])->get('me', [AuthController::class, 'me']);
});


Route::middleware(['role:superadmin', 'active'])->prefix('super-admin')->group(function () {
    Route::get('dashboard', [SuperAdminController::class, 'dashboardStats']);
    Route::prefix('front-office')->group(function () {
        Route::get('/', [SuperAdminController::class, 'index']);
        Route::post('/', [SuperAdminController::class, 'store']);
        Route::post('/{id}', [SuperAdminController::class, 'update']);
        Route::delete('/{id}', [SuperAdminController::class, 'destroy']);
        Route::patch('/{id}/toggle-status', [SuperAdminController::class, 'toggleStatus']);
    });
    Route::get('member', [MemberController::class, 'index']);
});

Route::middleware(['role:frontoffice', 'active'])->prefix('front-office')->group(function () {
    Route::get('dashboard', [FrontOfficeController::class, 'dashboard']);
    Route::prefix('member')->group(function () {
        Route::get('/', [MemberController::class, 'index']);
        Route::get('/show', [MemberController::class, 'show']);
        Route::post('/', [MemberController::class, 'store']);
        Route::post('/{id}', [MemberController::class, 'update']);
        Route::delete('/{id}', [MemberController::class, 'destroy']);
    });
});

Route::middleware(['role:member', 'active'])->prefix('member')->group(function () {
    Route::get('/show', [MemberController::class, 'show']);
    Route::post('/{id}', [MemberController::class, 'update']);
});
