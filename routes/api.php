<?php

use App\Http\Controllers\Api;
use App\Http\Controllers\Api\CloudinaryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Breeze /api/user override ─────────────────────────────────
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load(['profile', 'profile.media']);
});

// ── Versioned API ─────────────────────────────────────────────
Route::prefix('v1')->group(function () {

    // ── Auth — public ─────────────────────────────────────────
    Route::post('register', [Api\Auth\AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('login', [Api\Auth\AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [Api\Auth\AuthController::class, 'forgotPassword'])->middleware(['guest', 'throttle:5,1']);
    Route::post('reset-password', [Api\Auth\AuthController::class, 'resetPassword'])->middleware(['guest', 'throttle:5,1']);

    Route::get('verify-email/{id}/{hash}', [Api\Auth\AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // ── Auth — protected ──────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth actions
        Route::post('logout', [Api\Auth\AuthController::class, 'logout']);
        Route::get('me', [Api\Auth\AuthController::class, 'me']);
        Route::put('password', [Api\Auth\AuthController::class, 'updatePassword']);
        Route::put('profile', [Api\Auth\AuthController::class, 'updateProfile']);

        Route::post('email/verification-notification', [Api\Auth\AuthController::class, 'sendVerificationEmail'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        // Itineraries — full nested hierarchy
        Route::apiResource('itineraries', Api\ItineraryController::class);
        Route::apiResource('itineraries.days', Api\ItineraryDayController::class);
        Route::apiResource('itineraries.days.items', Api\ItineraryItemController::class);

        // Calendar
        Route::apiResource('calendar-events', Api\CalendarEventController::class);

        // Reviews
        Route::post('reviews', [Api\ReviewController::class, 'store']);
        Route::delete('reviews/{review}', [Api\ReviewController::class, 'destroy']);

        // Businesses — owner only
        Route::get('my-businesses', [Api\BusinessController::class, 'myBusinesses']);
        Route::apiResource('businesses', Api\BusinessController::class)->except(['index', 'show']);
        Route::apiResource('businesses.listings', Api\ListingController::class)->except(['index', 'show']);

        // Events — business owner only
        Route::apiResource('events', Api\EventController::class)->except(['index', 'show']);

        // Media
        Route::prefix('media')->group(function () {
            Route::post('signature', [CloudinaryController::class, 'signature']);
            Route::post('store', [CloudinaryController::class, 'store']);
            Route::post('cover', [CloudinaryController::class, 'setCover']);
            Route::delete('delete', [CloudinaryController::class, 'delete']);
        });

        // Posts
        Route::post('posts', [PostController::class, 'store']);
        Route::delete('posts/{post}', [PostController::class, 'destroy']);

        // Comments
        Route::post('posts/{post}/comments', [CommentController::class, 'store']);
        Route::post('comments/{comment}/reply', [CommentController::class, 'reply']);
        Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

        // Likes
        Route::post('{likeableType}/{likeableId}/like', [LikeController::class, 'toggleLike']);
        Route::post('{likeableType}/{likeableId}/dislike', [LikeController::class, 'toggleDislike']);

    }); // end auth:sanctum

    // ── Public routes ─────────────────────────────────────────
    Route::get('businesses', [Api\BusinessController::class, 'index']);
    Route::get('businesses/{business}', [Api\BusinessController::class, 'show']);
    Route::get('businesses/{business}/listings', [Api\ListingController::class, 'index']);
    Route::get('businesses/{business}/listings/{listing}', [Api\ListingController::class, 'show']);
    Route::apiResource('destinations', Api\DestinationController::class)->only(['index', 'show']);
    Route::apiResource('events', Api\EventController::class)->only(['index', 'show']);
    Route::get('reviews', [Api\ReviewController::class, 'index']);
    Route::get('search', SearchController::class);

    // Posts — public
    Route::get('users/{user}/posts', [PostController::class, 'userPosts']);
    Route::get('posts/{post}', [PostController::class, 'show']);

    // Users — public profile
    Route::get('users/{user}', [Api\Auth\AuthController::class, 'showProfile']);

}); // end v1
