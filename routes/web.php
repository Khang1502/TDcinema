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
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\CinemaCornerController as AdminCinemaCornerController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Customer\HomeController as CustomerHomeController;
use App\Http\Controllers\Customer\BookingController;
use App\Http\Controllers\Customer\MovieController as CustomerMovieController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\Customer\PromoController;
use App\Http\Controllers\Customer\FakePaymentController;
use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\StarShopController;
use App\Http\Controllers\Customer\CinemaController as CustomerCinemaController;
use App\Http\Controllers\Customer\CinemaCornerController as CustomerCinemaCornerController;
use App\Http\Controllers\Customer\MembershipController as CustomerMembershipController;
use App\Http\Controllers\Admin\MembershipController as AdminMembershipController;
use Illuminate\Support\Facades\Route;
// Trang chủ (customer)
Route::get('/', [CustomerHomeController::class, 'index'])
    ->name('home');
Route::get('/khuyen-mai', [PromoController::class, 'index'])->name('promotions.index');
Route::get('/thanh-vien', [CustomerMembershipController::class, 'index'])->name('membership.index');
Route::get('/account/membership', [CustomerMembershipController::class, 'index'])->middleware(['auth','verified','role:customer'])->name('customer.membership');
Route::post('/khuyen-mai/{promotion}/select', [PromoController::class, 'select'])->whereNumber('promotion')->name('promotions.select');
Route::get('/booking/checkout/{showtime}', [BookingController::class, 'checkout'])
    ->middleware(['auth', 'verified', 'role:customer'])
    ->name('booking.checkout');

// ĐẶT RETURN TRƯỚC KẺO BỊ BẮT NHẦM VÀO {showtime}
Route::get('/booking/payment/return', [FakePaymentController::class, 'return'])
    ->middleware(['auth', 'verified', 'role:customer'])
    ->name('booking.payment.return');

Route::middleware(['auth', 'verified', 'role:customer'])->group(function () {
    Route::post('/booking/hold/{showtime}', [BookingController::class, 'hold'])
        ->whereNumber('showtime')
        ->name('booking.hold');
    Route::get('/booking/combo/{showtime}', [BookingController::class, 'combo'])
        ->name('booking.combo');
    Route::get('/booking/payment/{showtime}', [PaymentController::class, 'payment'])
        ->whereNumber('showtime')
        ->name('booking.payment');
    Route::post('/booking/promo/apply', [PromoController::class, 'applyPromotion'])
        ->name('booking.promo.apply');
    Route::delete('/booking/promo', [PromoController::class, 'remove'])->name('booking.promo.remove');
    Route::post('/booking/payment/momo/{showtime}', [PaymentController::class, 'momoPayment'])
        ->whereNumber('showtime')
        ->name('booking.payment.momo');
    Route::post('/booking/payment/{showtime}', [PaymentController::class, 'processPayment'])
        ->whereNumber('showtime')
        ->name('booking.payment.process');
});
Route::post('/booking/payment/ipn', [PaymentController::class, 'momoIpn'])
    ->name('booking.payment.ipn');
Route::get('/booking/options', [BookingController::class, 'bookingOptions'])->name('booking.options');
Route::post('/booking/quick-intent', [BookingController::class, 'storeQuickIntent'])
    ->middleware('throttle:30,1')
    ->name('booking.quick-intent');
Route::get('/movies/{movie}', [CustomerMovieController::class, 'show'])
    ->name('movies.show');
Route::get('/movies', [CustomerMovieController::class, 'index'])
    ->name('movies.index');
Route::get('/star-shop', [StarShopController::class, 'index'])
    ->name('star-shop');
Route::get('/rap-gia-ve', [CustomerCinemaController::class, 'index'])
    ->name('cinemas.index');
Route::get('/goc-dien-anh/bai-viet/{cornerItem}', [CustomerCinemaCornerController::class, 'show'])->whereNumber('cornerItem')->name('cinema-corner.show');
Route::post('/goc-dien-anh/bai-viet/{cornerItem}/like', [CustomerCinemaCornerController::class, 'like'])
    ->middleware(['auth', 'verified', 'throttle:30,1'])->whereNumber('cornerItem')->name('cinema-corner.like');
Route::get('/goc-dien-anh/{category}', [CustomerCinemaCornerController::class, 'index'])->whereIn('category', ['review', 'blog'])->name('cinema-corner.index');

// Đăng nhập + verified mới vào dashboard role
Route::get('/dashboard', function () {
    return match (request()->user()?->role) {
        'admin' => redirect()->route('admin.dashboard'),
        'manager', 'staff' => redirect()->route('admin.dashboard'),
        default => redirect()->route('home'),
    };
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {

    // Khu quản trị chung: Basic admin, Manager admin và Super admin.
    // Mỗi nhóm route tiếp tục kiểm tra quyền cụ thể ở phía server.
    Route::middleware('admin.permission:dashboard.view')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('index');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::prefix('payment-methods')->name('payment-methods.')->middleware('admin.permission:payment-methods.manage')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PaymentMethodController::class, 'index'])->name('index');
            Route::get('{paymentMethod}/edit', [\App\Http\Controllers\Admin\PaymentMethodController::class, 'edit'])->whereNumber('paymentMethod')->name('edit');
            Route::put('{paymentMethod}', [\App\Http\Controllers\Admin\PaymentMethodController::class, 'update'])->whereNumber('paymentMethod')->name('update');
            Route::patch('{paymentMethod}/visibility', [\App\Http\Controllers\Admin\PaymentMethodController::class, 'visibility'])->whereNumber('paymentMethod')->name('visibility');
        });
        Route::prefix('payments')->name('payments.')->middleware('admin.permission:payments.manage')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('index');
            Route::get('export', [\App\Http\Controllers\Admin\PaymentController::class, 'export'])->name('export');
            Route::get('{payment}', [\App\Http\Controllers\Admin\PaymentController::class, 'show'])->whereNumber('payment')->name('show');
        });
        Route::middleware('admin.permission:reports.view')->prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\RevenueReportController::class, 'index'])->name('index');
            Route::get('/export', [\App\Http\Controllers\Admin\RevenueReportController::class, 'export'])->name('export');
        });

        // Export dashboard CSV
        Route::get('/export-excel', [AdminDashboardController::class, 'export'])
            ->middleware('admin.permission:reports.view')->name('export');

        Route::middleware('admin.permission:movies.manage')->group(function () {
            Route::get('movies/archive', [\App\Http\Controllers\Admin\MovieArchiveController::class, 'index'])->name('movies.archive');
            Route::get('movies/archive/{movie}', [\App\Http\Controllers\Admin\MovieArchiveController::class, 'show'])->name('movies.archive.show');
            Route::post('movies/{movie}/archive', [\App\Http\Controllers\Admin\MovieArchiveController::class, 'store'])->name('movies.archive.store');
            Route::post('movies/{movie}/restore', [\App\Http\Controllers\Admin\MovieArchiveController::class, 'restore'])->name('movies.restore');
        });
        Route::resource('movies', AdminMovieController::class)->except(['show'])
            ->middleware('admin.permission:movies.manage');
        Route::middleware('admin.permission:movies.manage')->group(function () {
            Route::patch('genres/{genre}/visibility',[\App\Http\Controllers\Admin\GenreController::class,'visibility'])->name('genres.visibility');
            Route::resource('genres',\App\Http\Controllers\Admin\GenreController::class)->except(['show']);
        });

        Route::middleware('admin.permission:banners.manage')->group(function () {
            Route::patch('banners/order', [AdminBannerController::class, 'order'])->name('banners.order');
            Route::patch('banners/{banner}/visibility', [AdminBannerController::class, 'visibility'])->name('banners.visibility');
            Route::resource('banners', AdminBannerController::class)->except(['show']);
        });

        Route::prefix('membership')->name('membership.')->middleware('admin.permission:membership.manage')->group(function () {
            Route::get('/', [AdminMembershipController::class,'index'])->name('index');
            Route::get('settings', [AdminMembershipController::class,'settings'])->name('settings');
            Route::put('settings', [AdminMembershipController::class,'saveSettings'])->name('settings.update');
            Route::get('members', [AdminMembershipController::class,'members'])->name('members');
            Route::get('members/{member}', [AdminMembershipController::class,'member'])->name('member');
            Route::post('members/{member}/points', [AdminMembershipController::class,'adjust'])->name('points');
            Route::get('levels/create', [AdminMembershipController::class,'create'])->name('levels.create');
            Route::post('levels', [AdminMembershipController::class,'store'])->name('levels.store');
            Route::get('levels/{level}/edit', [AdminMembershipController::class,'edit'])->name('levels.edit');
            Route::put('levels/{level}', [AdminMembershipController::class,'update'])->name('levels.update');
            Route::delete('levels/{level}', [AdminMembershipController::class,'destroy'])->name('levels.destroy');
        });

        Route::middleware('admin.permission:cinema-corner.manage')->group(function () {
            Route::put('cinema-corner/settings', [AdminCinemaCornerController::class, 'settings'])->name('cinema-corner.settings');
            Route::patch('cinema-corner/order', [AdminCinemaCornerController::class, 'order'])->name('cinema-corner.order');
            Route::patch('cinema-corner/{cornerItem}/visibility', [AdminCinemaCornerController::class, 'visibility'])->name('cinema-corner.visibility');
            Route::resource('cinema-corner', AdminCinemaCornerController::class)->parameters(['cinema-corner' => 'cornerItem'])->except(['show']);
        });

        Route::middleware('admin.permission:cinemas.manage')->group(function () {
            Route::resource('cinemas', \App\Http\Controllers\Admin\CinemaController::class)->except(['show']);
            Route::post('cinemas/{cinema}/rooms', [\App\Http\Controllers\Admin\CinemaController::class, 'storeRoom'])->name('cinemas.rooms.store');
            Route::put('cinemas/{cinema}/rooms/{room}', [\App\Http\Controllers\Admin\CinemaController::class, 'updateRoom'])->name('cinemas.rooms.update');
            Route::delete('cinemas/{cinema}/rooms/{room}', [\App\Http\Controllers\Admin\CinemaController::class, 'destroyRoom'])->name('cinemas.rooms.destroy');
        });

        // Quản lý phòng chiếu riêng
        Route::resource('rooms', \App\Http\Controllers\Admin\RoomController::class)->except(['show'])
            ->middleware('admin.permission:rooms.manage');

        // Quản lý suất chiếu + lịch chiếu
        Route::middleware('admin.permission:showtimes.manage')->group(function () {
            Route::resource('showtimes', AdminShowtimeController::class)->except(['show']);
            Route::get('showtimes/schedule', [AdminShowtimeController::class, 'schedule'])->name('showtimes.schedule');
            Route::post('showtimes/clone-day', [AdminShowtimeController::class, 'cloneDay'])->name('showtimes.clone-day');
            Route::post('showtimes/refresh-schedule', [AdminShowtimeController::class, 'refreshSchedule'])->name('showtimes.refresh-schedule');
        });

       // Quản lý ghế (ĐẶT CUSTOM ROUTES TRƯỚC)
        Route::middleware('admin.permission:seats.manage')->group(function () {
            Route::get('seats/preview', [AdminSeatController::class, 'preview'])->name('seats.preview');
            Route::patch('seats/bulk-update', [AdminSeatController::class, 'bulkUpdate'])->name('seats.bulk-update');
            Route::delete('seats/bulk-delete', [AdminSeatController::class, 'bulkDelete'])->name('seats.bulk-delete');
            Route::get('seats/data/{room}', [AdminSeatController::class, 'data'])->name('seats.data');
            Route::get('seats/map/{room}', [AdminSeatController::class, 'map'])->name('seats.map');
            Route::get('seats/rooms-by-cinema/{cinema}', [AdminSeatController::class, 'roomsByCinema'])->name('seats.rooms-by-cinema');
            Route::post('seats/bulk', [AdminSeatController::class, 'bulk'])->name('seats.bulk');
            Route::post('seats/bulk-add/{room}', [AdminSeatController::class, 'bulkAdd'])->name('seats.bulk-add');
            Route::post('seats/generate/{room}', [AdminSeatController::class, 'generateLayout'])->name('seats.generate');
            Route::post('seats/copy/{room}', [AdminSeatController::class, 'copyLayout'])->name('seats.copy');
            Route::post('seats/apply-all/{room}', [AdminSeatController::class, 'applyLayoutToAll'])->name('seats.apply-all');
            Route::patch('seats/type-prices/{room}', [AdminSeatController::class, 'updateTypePrices'])->name('seats.type-prices');
            Route::patch('seats/sync-total/{room}', [AdminSeatController::class, 'syncTotal'])->name('seats.sync-total');
            Route::resource('seats', AdminSeatController::class)->except(['show']);
        });

        // Quản lý vé
        Route::middleware('admin.permission:tickets.manage')->group(function () {
            Route::post('tickets/bulk-checkin', [AdminTicketController::class, 'bulkCheckin'])->name('tickets.bulk-checkin');
            Route::post('tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus'])->name('tickets.status');
            Route::resource('tickets', AdminTicketController::class)->except(['edit', 'update', 'destroy']);
        });

        // Quản lý người dùng
        Route::get('users/create', [AdminUserController::class, 'create'])
            ->middleware('admin.permission:users.manage')->name('users.create');
        Route::middleware('admin.permission:users.view')->group(function () {
            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        });
        Route::middleware('admin.permission:users.manage')->group(function () {
            Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
            Route::match(['put', 'patch'], 'users/{user}', [AdminUserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
            Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');
        });

        // Quản lý dịch vụ/đồ ăn
        Route::resource('foods', AdminFoodItemController::class)->except(['show'])
            ->middleware('admin.permission:foods.manage');

        // Quản lý khuyến mãi
        Route::get('footer', [\App\Http\Controllers\Admin\FooterController::class, 'edit'])->name('footer.edit')->middleware('admin.permission:footer.manage');
        Route::get('header', [\App\Http\Controllers\Admin\HeaderController::class, 'edit'])->name('header.edit')->middleware('admin.permission:header.manage');
        Route::put('header', [\App\Http\Controllers\Admin\HeaderController::class, 'update'])->name('header.update')->middleware('admin.permission:header.manage');
        Route::view('website', 'admin.website.index')->name('website.index');
        Route::resource('gifts', \App\Http\Controllers\Admin\GiftController::class)->except(['show'])->middleware('admin.permission:gifts.manage');
        Route::put('footer', [\App\Http\Controllers\Admin\FooterController::class, 'update'])->name('footer.update')->middleware('admin.permission:footer.manage');
        Route::get('promotions/display', [AdminPromotionController::class, 'display'])->name('promotions.display')->middleware('admin.permission:promotions.manage');
        Route::put('promotions/display', [AdminPromotionController::class, 'updateDisplay'])->name('promotions.display.update')->middleware('admin.permission:promotions.manage');
        Route::resource('promotions', AdminPromotionController::class)->except(['show'])
            ->middleware('admin.permission:promotions.manage');

        Route::middleware('admin.permission:permissions.manage')->group(function () {
            Route::get('permissions', [AdminPermissionController::class, 'index'])->name('permissions.index');
            Route::get('permissions/{user}/edit', [AdminPermissionController::class, 'edit'])->name('permissions.edit');
            Route::patch('permissions/{user}', [AdminPermissionController::class, 'update'])->name('permissions.update');
        });

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
        Route::get('/notifications', [AccountController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read', [AccountController::class, 'markNotificationsRead'])->name('notifications.read');
        Route::get('/gifts', [AccountController::class, 'gifts'])->name('gifts');
        Route::get('/policy', [AccountController::class, 'policy'])->name('policy');
    });

    // Profile (chung)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
