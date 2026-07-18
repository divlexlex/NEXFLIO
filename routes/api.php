<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AppointmentController;
use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CommissionController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\GiftCardController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\LeaveController;
use App\Http\Controllers\API\MembershipPlanController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\PromoController;
use App\Http\Controllers\API\RecommendationController;
use App\Http\Controllers\API\ServiceController;
use App\Http\Controllers\API\StaffController;
use App\Http\Controllers\API\WishlistController;

Route::get('/user', function (Request $request) {
    // Staff accounts carry their profile (break state, position, rates).
    return $request->user()->loadMissing('staffProfile');
})->middleware('auth:sanctum');

// ===== Public =====
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Dialogflow ES fulfillment (authenticated by shared webhook token header).
Route::post('/dialogflow/webhook', [App\Http\Controllers\API\DialogflowController::class, 'webhook']);

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{id}', [ServiceController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/membership-plans', [MembershipPlanController::class, 'index']);
Route::get('/gift-cards', [GiftCardController::class, 'index']);
Route::get('/promos', [PromoController::class, 'index']);
Route::get('/promos/{id}', [PromoController::class, 'show']);
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/recommendations', [RecommendationController::class, 'index']);

// ===== Any authenticated user =====
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
    Route::post('/device-tokens', [App\Http\Controllers\API\DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [App\Http\Controllers\API\DeviceTokenController::class, 'destroy']);
});

// ===== Staff / Manager / Super Admin =====
// The AppointmentService enforces which role may perform which transition
// (payment verification stays manager-only).
Route::middleware(['auth:sanctum', 'role:1,2,3'])->group(function () {
    Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
    Route::post('/appointments/{id}/start', [AppointmentController::class, 'start']);
    Route::post('/appointments/{id}/complete', [AppointmentController::class, 'complete']);
});

// ===== Staff only =====
Route::middleware(['auth:sanctum', 'role:3'])->group(function () {
    Route::post('/attendance/time-in', [AttendanceController::class, 'timeIn']);
    Route::patch('/attendance/time-out', [AttendanceController::class, 'timeOut']);
    Route::get('/attendance/me', [AttendanceController::class, 'mine']);
    Route::patch('/staff/break', [StaffController::class, 'toggleBreak']);
    Route::get('/my-commissions', [CommissionController::class, 'mine']);
    Route::get('/leaves', [LeaveController::class, 'mine']);
    Route::post('/leaves', [LeaveController::class, 'store']);
    Route::get('/inventory/options', [InventoryController::class, 'options']);
});

// ===== Manager / Super Admin =====
Route::middleware(['auth:sanctum', 'role:1,2'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory', [InventoryController::class, 'store']);
    Route::post('/inventory/{id}/batches', [InventoryController::class, 'addBatch']);
    Route::post('/inventory/{id}/pull-out', [InventoryController::class, 'pullOut']);
    Route::get('/inventory/{id}/movements', [InventoryController::class, 'movements']);

    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/leaves/all', [LeaveController::class, 'index']);
    Route::patch('/leaves/{id}/review', [LeaveController::class, 'review']);
    Route::get('/staff/{id}/commissions', [CommissionController::class, 'forStaff']);
    Route::post('/employees', [EmployeeController::class, 'store']);
});

// ===== Super Admin only =====
Route::middleware(['auth:sanctum', 'role:1'])->group(function () {
    Route::get('/audit-logs', [AdminController::class, 'getLogs']);
});
