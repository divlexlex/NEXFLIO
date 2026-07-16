<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\MembershipPlanController;
use App\Http\Controllers\API\GiftCardController;
use App\Http\Controllers\API\PromoController;
use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\WishlistController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\API\AppointmentController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/membership-plans', [MembershipPlanController::class, 'index']);
Route::get('/gift-cards', [GiftCardController::class, 'index']);
Route::get('/promos', [PromoController::class, 'index']);
Route::get('/articles', [ArticleController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/personnel', [AppointmentController::class, 'personnel']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{serviceId}', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{serviceId}', [WishlistController::class, 'destroy']);
});

// Approving/rejecting appointments is a Staff/Manager/Admin action, not a client one.
Route::middleware(['auth:sanctum', 'role:1,2,3'])->group(function () {
    Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
});

Route::middleware(['auth:sanctum', 'role:1,2'])->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::patch('/inventory/{id}/add', [InventoryController::class, 'addStock']);
    Route::patch('/inventory/{id}/deduct', [InventoryController::class, 'deductStock']);
});


Route::middleware(['auth:sanctum', 'role:1'])->group(function () {
    Route::get('/audit-logs', [AdminController::class, 'getLogs']);
});

Route::middleware(['auth:sanctum', 'role:1,2'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});