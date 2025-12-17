<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MovieController as AdminMovieController;
use App\Http\Controllers\Admin\ShowtimeController as AdminShowtimeController;
use App\Http\Controllers\Admin\SeatController as AdminSeatController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\FoodItemController as AdminFoodItemController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Customer\HomeController as CustomerHomeController;
use App\Http\Controllers\Customer\MovieController as CustomerMovieController;
use App\Http\Controllers\Customer\FakePaymentController;
use Illuminate\Support\Facades\Route;
use App\Models\Movie;

// Trang chủ (customer)
Route::get('/', [CustomerHomeController::class, 'index'])
    ->middleware('only_customer')
    ->name('home');
Route::get('/booking/checkout/{showtime}', [CustomerHomeController::class, 'checkout'])
    ->name('booking.checkout');
Route::get('/booking/combo/{showtime}', [CustomerHomeController::class, 'combo'])
    ->name('booking.combo');

// ĐẶT RETURN TRƯỚC KẺO BỊ BẮT NHẦM VÀO {showtime}
Route::get('/booking/payment/return', [FakePaymentController::class, 'return'])
    ->name('booking.payment.return');

Route::get('/booking/payment/{showtime}', [CustomerHomeController::class, 'payment'])
    ->whereNumber('showtime')
    ->name('booking.payment');
Route::post('/booking/promo/apply', [CustomerHomeController::class, 'applyPromotion'])
    ->name('booking.promo.apply');
Route::post('/booking/payment/momo/{showtime}', [CustomerHomeController::class, 'momoPayment'])
    ->whereNumber('showtime')
    ->name('booking.payment.momo');
Route::post('/booking/payment/ipn', [CustomerHomeController::class, 'momoIpn'])
    ->name('booking.payment.ipn');
Route::post('/booking/payment/{showtime}', [CustomerHomeController::class, 'processPayment'])
    ->whereNumber('showtime')
    ->name('booking.payment.process');
Route::get('/booking/options', [CustomerHomeController::class, 'bookingOptions'])->name('booking.options');
Route::get('/movies/{movie}', [CustomerMovieController::class, 'show'])
    ->name('movies.show');
Route::get('/movies', [CustomerMovieController::class, 'index'])
    ->name('movies.index');

// Đăng nhập + verified mới vào dashboard role
Route::middleware(['auth', 'verified'])->group(function () {

    // Admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Export dashboard CSV
        Route::get('/export-excel', [AdminDashboardController::class, 'export'])->name('export');

        Route::resource('movies', AdminMovieController::class)->except(['show']);

        Route::resource('cinemas', \App\Http\Controllers\Admin\CinemaController::class)->except(['show']);
        Route::post('cinemas/{cinema}/rooms', [\App\Http\Controllers\Admin\CinemaController::class, 'storeRoom'])->name('cinemas.rooms.store');
        Route::put('cinemas/{cinema}/rooms/{room}', [\App\Http\Controllers\Admin\CinemaController::class, 'updateRoom'])->name('cinemas.rooms.update');
        Route::delete('cinemas/{cinema}/rooms/{room}', [\App\Http\Controllers\Admin\CinemaController::class, 'destroyRoom'])->name('cinemas.rooms.destroy');

        // Quản lý phòng chiếu riêng
        Route::resource('rooms', \App\Http\Controllers\Admin\RoomController::class)->except(['show']);

        // Quản lý suất chiếu + lịch chiếu
        Route::resource('showtimes', AdminShowtimeController::class)->except(['show']);
        Route::get('showtimes/schedule', [AdminShowtimeController::class, 'schedule'])->name('showtimes.schedule');
        Route::post('showtimes/clone-day', [AdminShowtimeController::class, 'cloneDay'])->name('showtimes.clone-day');

       // Quản lý ghế (ĐẶT CUSTOM ROUTES TRƯỚC)
        Route::patch('seats/bulk-update', [AdminSeatController::class, 'bulkUpdate'])->name('seats.bulk-update');
        Route::delete('seats/bulk-delete', [AdminSeatController::class, 'bulkDelete'])->name('seats.bulk-delete');

        Route::get('seats/data/{room}', [AdminSeatController::class, 'data'])->name('seats.data');
        Route::get('seats/map/{room}', [AdminSeatController::class, 'map'])->name('seats.map');
        Route::get('seats/rooms-by-cinema/{cinema}', [AdminSeatController::class, 'roomsByCinema'])->name('seats.rooms-by-cinema');

        Route::post('seats/bulk', [AdminSeatController::class, 'bulk'])->name('seats.bulk');
        Route::post('seats/bulk-add/{room}', [AdminSeatController::class, 'bulkAdd'])->name('seats.bulk-add');
        Route::post('seats/generate/{room}', [AdminSeatController::class, 'generateLayout'])->name('seats.generate');
        Route::post('seats/copy/{room}', [AdminSeatController::class, 'copyLayout'])->name('seats.copy');
        Route::patch('seats/sync-total/{room}', [AdminSeatController::class, 'syncTotal'])->name('seats.sync-total');

        // ĐỂ RESOURCE XUỐNG CUỐI
        Route::resource('seats', AdminSeatController::class)->except(['show']);

        // Quản lý vé
        Route::resource('tickets', AdminTicketController::class)->except(['edit', 'update', 'destroy']);
        Route::post('tickets/bulk-checkin', [AdminTicketController::class, 'bulkCheckin'])->name('tickets.bulk-checkin');
        Route::post('tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('tickets.status');

        // Quản lý người dùng
        Route::resource('users', AdminUserController::class);
        Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');

        // Quản lý dịch vụ/đồ ăn
        Route::resource('foods', AdminFoodItemController::class)->except(['show']);

        // Quản lý khuyến mãi
        Route::resource('promotions', AdminPromotionController::class)->except(['show']);

    });

    // Manager
    Route::middleware('role:manager')->prefix('manager')->name('manager.')->group(function () {
        Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');
    });

    // Staff
    Route::middleware('role:staff')->prefix('staff')->name('staff.')->group(function () {
        Route::get('/dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');
    });

    // Customer account (placeholder)
    Route::middleware('role:customer')->prefix('account')->name('customer.')->group(function () {
        Route::get('/dashboard', function () {
            return view('customer.dashboard.index');
        })->name('dashboard');
        Route::get('/tickets', [CustomerHomeController::class, 'ticketHistory'])->name('tickets');
    });

    // Profile (chung)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
