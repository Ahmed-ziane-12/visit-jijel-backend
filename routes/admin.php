<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Admin\Auth\AuthController;
use App\Http\Controllers\Admin\CloudinaryStatsController;

Route::name('admin.')->prefix('admin/v1')->group(function () {

    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);

        // First login password reset — before anything else
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

        // Admin management — super admin only
        Route::middleware('super_admin')->group(function () {
            Route::get('admins', [AuthController::class, 'listAdmins']);
            Route::post('admins', [AuthController::class, 'createAdmin']);
            Route::delete('admins/{user}', [AuthController::class, 'deleteAdmin']);
        });

        // Platform management
        Route::apiResource('users', Admin\UserController::class);
        Route::apiResource('businesses', Admin\BusinessController::class)->except(['store']);
        Route::apiResource('destinations', Admin\DestinationController::class);
        Route::apiResource('events', Admin\EventController::class)->except(['store']);
        Route::apiResource('reviews', Admin\ReviewController::class)->except(['store']);
        Route::apiResource('plans', Admin\PlanController::class)->except(['store']);

        // Dashboard stats
        Route::get('stats', [Admin\AdminStatsController::class, 'index']);

        // Cloudinary Stats
        Route::get('cloudinary/stats', CloudinaryStatsController::class);

        // Activity Logs — super admin only
        Route::middleware('super_admin')->get('activity-logs', [Admin\ActivityLogController::class, 'index']);
    });
});
