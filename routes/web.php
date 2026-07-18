<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AppointmentController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EmployeeController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\LandingController;
use App\Http\Controllers\Web\LeaveController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\PromoController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\ServiceController;

// ===== Public =====
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Public catalog detail pages (booking still happens in the app).
Route::get('/services/{id}', [CatalogController::class, 'service'])->name('catalog.service');
Route::get('/products/{id}', [CatalogController::class, 'product'])->name('catalog.product');
Route::get('/promos/{id}', [CatalogController::class, 'promo'])->name('catalog.promo');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ===== Owner / Manager portal =====
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:1,2'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'chartData'])->name('dashboard.data');
    Route::get('/dashboard/insights', [DashboardController::class, 'insights'])->name('dashboard.insights');

    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments');
    Route::get('/appointments/feed', [AppointmentController::class, 'feed'])->name('appointments.feed');
    Route::post('/appointments/walk-in', [AppointmentController::class, 'storeWalkIn'])->name('appointments.walk-in');
    Route::patch('/appointments/{id}/override', [AppointmentController::class, 'override'])->name('appointments.override');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments');
    Route::patch('/payments/{id}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
    Route::patch('/payments/{id}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::post('/inventory/{id}/batches', [InventoryController::class, 'addBatch'])->name('inventory.batches');
    Route::post('/inventory/{id}/pull-out', [InventoryController::class, 'pullOut'])->name('inventory.pull-out');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::patch('/employees/{id}', [EmployeeController::class, 'update'])->name('employees.update');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves');
    Route::patch('/leaves/{id}/review', [LeaveController::class, 'review'])->name('leaves.review');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing');

    Route::get('/services', [ServiceController::class, 'index'])->name('services');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::patch('/services/{id}', [ServiceController::class, 'update'])->name('services.update');

    Route::get('/promos', [PromoController::class, 'index'])->name('promos');
    Route::post('/promos', [PromoController::class, 'store'])->name('promos.store');
    Route::patch('/promos/{id}', [PromoController::class, 'update'])->name('promos.update');

    Route::get('/products', [ProductController::class, 'index'])->name('products');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::patch('/products/{id}', [ProductController::class, 'update'])->name('products.update');

    // ===== Owner only =====
    Route::middleware('role:1')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs');
        Route::get('/reports/financial', [ReportController::class, 'financial'])->name('reports.financial');
    });
});
