<?php

use App\Http\Controllers\Web\AppointmentController;
use App\Http\Controllers\Web\AppointmentRequestController;
use App\Http\Controllers\Web\AuditLogController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\BookingController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\ClientAccountController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EmailVerificationController;
use App\Http\Controllers\Web\EmployeeController;
use App\Http\Controllers\Web\AvailabilityController;
use App\Http\Controllers\Web\InventoryController;
use App\Http\Controllers\Web\LandingController;
use App\Http\Controllers\Web\LeaveController;
use App\Http\Controllers\Web\OffersController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\PromoController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\ServiceController;
use App\Http\Controllers\Web\ServicesController;
use App\Http\Controllers\Web\StaffController;
use Illuminate\Support\Facades\Route;

// ===== Public =====
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Public services catalog page (full menu — also embedded on Guest Home).
Route::get('/services', [ServicesController::class, 'index'])->name('services');

// Public catalog detail pages (booking still happens in the app).
Route::get('/services/{id}', [CatalogController::class, 'service'])->name('catalog.service');
Route::get('/promos/{id}', [CatalogController::class, 'promo'])->name('catalog.promo');

// Public Special Offers listing — full counterpart to the Home page teaser
// (see landing/index.blade.php), both reading the same active Promo rows.
Route::get('/offers', [OffersController::class, 'index'])->name('offers');

// Public navbar destinations awaiting their own redesign phase — minimal shells for now.
Route::get('/packages', [PageController::class, 'packages'])->name('packages');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/gallery', [PageController::class, 'gallery'])->name('gallery');
Route::get('/faqs', [PageController::class, 'faqs'])->name('faqs');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// New-account / not-yet-verified-login handoff — see EmailVerificationController's
// class doc for why this uses a plain session key instead of the auth guard.
Route::get('/verify-email', [EmailVerificationController::class, 'show'])->name('verification.show');
Route::post('/verify-email', [EmailVerificationController::class, 'verify'])->name('verification.verify');
Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend');

// Route names match Laravel's password-broker conventions (Illuminate\Auth\Notifications\ResetPassword
// builds its email link via route('password.reset', ...) by default).
Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');

// ===== Client Website account (Phase 2) =====
// Same CheckRole middleware the admin group below uses, scoped to Client
// (role 4) only — Management can never land here just by being authenticated.
Route::prefix('account')->name('account.')->middleware(['auth', 'role:4'])->group(function () {
    Route::get('/', [ClientAccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/bookings', [ClientAccountController::class, 'bookings'])->name('bookings');

    // Client-raised cancellation / reschedule requests — a Manager approves
    // them in the /admin queue before anything on the appointment changes.
    Route::post('/bookings/{appointment}/change-requests', [ClientAccountController::class, 'storeChangeRequest'])->name('bookings.change-request');
    Route::delete('/bookings/{appointment}/change-requests/{changeRequest}', [ClientAccountController::class, 'withdrawChangeRequest'])->name('bookings.change-request.withdraw');

    Route::get('/profile', [ClientAccountController::class, 'profile'])->name('profile');
    Route::patch('/profile', [ClientAccountController::class, 'updateProfile'])->name('profile.update');

    // Dashboard notifications feed (see ClientAccountController@dashboard for
    // the read).
    Route::patch('/notifications/{id}/read', [ClientAccountController::class, 'markNotificationRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [ClientAccountController::class, 'markAllNotificationsRead'])->name('notifications.read-all');

    // ===== Website Booking Flow — Branch Booking (Phase 3A) + Home Service
    // Booking (Phase 3B) =====
    Route::prefix('book')->name('booking.')->group(function () {
        Route::get('/', [BookingController::class, 'start'])->name('start');

        // "Where would you like this service?" — the Branch/Home Service
        // choice for a `both`-eligible real service (App\Enums\ServiceLocationType).
        Route::get('/location', [BookingController::class, 'location'])->name('location');

        Route::get('/branch/service', [BookingController::class, 'serviceIndex'])->name('branch.service');
        Route::get('/branch/service/{id}', [BookingController::class, 'serviceShow'])->name('branch.service.show');
        Route::get('/branch/schedule', [BookingController::class, 'schedule'])->name('branch.schedule');
        Route::get('/branch/details', [BookingController::class, 'details'])->name('branch.details');
        Route::get('/branch/review', [BookingController::class, 'review'])->name('branch.review');
        Route::get('/branch/payment', [BookingController::class, 'payment'])->name('branch.payment');
        Route::post('/branch', [BookingController::class, 'store'])->name('branch.store');
        Route::get('/branch/success/{id}', [BookingController::class, 'success'])->name('branch.success');

        Route::get('/home/service', [BookingController::class, 'homeServiceIndex'])->name('home.service');
        Route::get('/home/service/{id}', [BookingController::class, 'homeServiceShow'])->name('home.service.show');
        Route::get('/home/address', [BookingController::class, 'address'])->name('home.address');
        Route::get('/home/schedule', [BookingController::class, 'homeSchedule'])->name('home.schedule');
        Route::get('/home/details', [BookingController::class, 'homeDetails'])->name('home.details');
        Route::get('/home/review', [BookingController::class, 'homeReview'])->name('home.review');
        Route::get('/home/payment', [BookingController::class, 'homePayment'])->name('home.payment');
        Route::post('/home', [BookingController::class, 'homeStore'])->name('home.store');
        // Shares BookingController::success() with Branch — see that method's
        // doc comment for why one method/view safely covers both.
        Route::get('/home/success/{id}', [BookingController::class, 'success'])->name('home.success');
    });
});

// ===== Staff self-service portal =====
// A Staff account (role 3) previously had nowhere to sign in at all on the
// Website (blocked at login, "use the mobile app" — see AuthController's
// history). Now that the mobile app isn't being built, this single
// dashboard page covers what the equivalent mobile API endpoints already
// did: clock in/out, see assigned appointments, commissions, and leave
// requests.
Route::prefix('staff')->name('staff.')->middleware(['auth', 'role:3'])->group(function () {
    Route::get('/dashboard', [StaffController::class, 'dashboard'])->name('dashboard');
    Route::post('/attendance/time-in', [StaffController::class, 'timeIn'])->name('attendance.time-in');
    Route::post('/attendance/time-out', [StaffController::class, 'timeOut'])->name('attendance.time-out');
    Route::post('/leaves', [StaffController::class, 'storeLeave'])->name('leaves.store');
    Route::patch('/password', [StaffController::class, 'updatePassword'])->name('password.update');
    Route::patch('/appointments/{id}/complete', [StaffController::class, 'completeService'])->name('appointments.complete');
});

// ===== Owner / Manager portal =====
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:1,2'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'chartData'])->name('dashboard.data');
    Route::get('/dashboard/insights', [DashboardController::class, 'insights'])->name('dashboard.insights');

    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments');
    Route::get('/appointments/feed', [AppointmentController::class, 'feed'])->name('appointments.feed');
    Route::post('/appointments/walk-in', [AppointmentController::class, 'storeWalkIn'])->name('appointments.walk-in');
    Route::patch('/appointments/{id}/override', [AppointmentController::class, 'override'])->name('appointments.override');
    Route::patch('/appointments/{id}/assign-personnel', [AppointmentController::class, 'assignPersonnel'])->name('appointments.assign-personnel');
    Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');

    // Client-raised cancellation / reschedule request queue.
    Route::get('/appointment-requests', [AppointmentRequestController::class, 'index'])->name('appointment-requests');
    Route::patch('/appointment-requests/{changeRequest}/review', [AppointmentRequestController::class, 'review'])->name('appointment-requests.review');

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
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability');
    Route::post('/availability/business-hours', [AvailabilityController::class, 'updateBusinessHours'])->name('availability.business-hours');
    Route::post('/availability/staff-schedule', [AvailabilityController::class, 'updateStaffSchedule'])->name('availability.staff-schedule');
    Route::post('/availability/service-hours', [AvailabilityController::class, 'updateServiceHours'])->name('availability.service-hours');
    Route::post('/availability/blocked-slots', [AvailabilityController::class, 'storeBlockedSlot'])->name('availability.blocked-slots.store');
    Route::delete('/availability/blocked-slots/{id}', [AvailabilityController::class, 'destroyBlockedSlot'])->name('availability.blocked-slots.destroy');

    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves');
    Route::patch('/leaves/{id}/review', [LeaveController::class, 'review'])->name('leaves.review');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing');

    Route::get('/services', [ServiceController::class, 'index'])->name('services');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::patch('/services/{id}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->name('services.destroy');

    Route::get('/promos', [PromoController::class, 'index'])->name('promos');
    Route::get('/promos/create', [PromoController::class, 'create'])->name('promos.create');
    Route::post('/promos', [PromoController::class, 'store'])->name('promos.store');
    Route::get('/promos/{id}/edit', [PromoController::class, 'edit'])->name('promos.edit');
    Route::patch('/promos/{id}', [PromoController::class, 'update'])->name('promos.update');

    // ===== Owner only =====
    Route::middleware('role:1')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs');
        Route::get('/reports/financial', [ReportController::class, 'financial'])->name('reports.financial');
        Route::get('/reports/financial/export/{format}', [ReportController::class, 'export'])->name('reports.financial.export');
    });
});
