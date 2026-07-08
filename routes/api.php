<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\API\AppointmentController;

Route::post('/appointments', [AppointmentController::class, 'store']);
Route::get('/appointments', [AppointmentController::class, 'index']);
Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
Route::get('/inventory', [InventoryController::class, 'index']);
Route::patch('/inventory/{id}/add', [InventoryController::class, 'addStock']);
Route::patch('/inventory/{id}/deduct', [InventoryController::class, 'deductStock']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'role:1,2'])->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::patch('/inventory/{id}/add', [InventoryController::class, 'addStock']);
});


Route::middleware(['auth:sanctum', 'role:1'])->group(function () {
    Route::get('/audit-logs', [AdminController::class, 'getLogs']);
});

Route::middleware(['auth:sanctum', 'role:1,2'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});