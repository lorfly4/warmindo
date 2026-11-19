<?php

use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DiningTableController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\LocationQrController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShiftController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Cashier\CashierController;
use App\Http\Controllers\Cashier\OrderActionController;
use App\Http\Controllers\Cashier\ReceiptController;
use App\Http\Controllers\Customer\PaymentRedirectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Payment\MockWebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/media/{path}', function (string $path) {
    $disk = Storage::disk('public');
    abort_unless($disk->exists($path), 404);

    $mimeType = $disk->mimeType($path);
    abort_unless(in_array($mimeType, ['image/avif', 'image/gif', 'image/jpeg', 'image/png', 'image/webp'], true), 404);

    $stream = $disk->readStream($path);
    abort_unless(is_resource($stream), 404);

    return response()->stream(function () use ($stream): void {
        fpassthru($stream);
        fclose($stream);
    }, 200, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=86400',
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->where('path', '.*')->name('media.show');

Route::get('/login', [LoginController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('guest')->name('login.store');
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::prefix('menu')->name('menu.')->group(function () {
    Route::get('/', fn () => abort(404))->name('index');
    Route::get('/category/{category:slug}', fn () => abort(404))->name('category');
    Route::get('/order', fn () => abort(404))->name('order');
    Route::get('/{product}', fn () => abort(404))->name('show');
});

Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', fn () => abort(404))->name('index');
    Route::post('/add', fn () => abort(404))->name('add');
    Route::post('/update/{productId}', fn () => abort(404))->name('update');
    Route::post('/remove/{productId}', fn () => abort(404))->name('remove');
    Route::post('/discount', fn () => abort(404))->name('discount');
});

Route::post('/checkout', fn () => abort(404))->name('checkout.store');
Route::get('/track/{order:order_number}', fn () => abort(404))->name('tracking.show');
Route::get('/order-history', fn () => abort(404))->name('tracking.history');
Route::post('/track/{order:order_number}/cancel', fn () => abort(404))->name('tracking.cancel');

Route::middleware(['auth', 'active', 'role:cashier|manager|admin|kitchen'])->group(function () {
    Route::get('/payment/mock/{payment}', [PaymentRedirectController::class, 'show'])->name('payment.mock.pay');
    Route::post('/payment/mock/{payment}/process', [PaymentRedirectController::class, 'process'])->name('payment.mock.process');
    Route::get('/payment/qris/{payment}', [PaymentRedirectController::class, 'qrisShow'])->name('payment.qris.pay');
});

Route::post('/webhook/payment/mock', [MockWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhook.payment.mock');

Route::middleware(['auth', 'active', 'role:cashier|manager|admin|kitchen'])->prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/', [CashierController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [CashierController::class, 'orders'])->name('orders');
    Route::get('/orders/create', [CashierController::class, 'create'])->name('orders.create');
    Route::post('/orders', [CashierController::class, 'store'])->name('orders.store');
    Route::get('/orders/{order}', [CashierController::class, 'show'])->name('orders.show');
    Route::put('/orders/{order}/items', [OrderActionController::class, 'updateItems'])->name('orders.items.update');

    Route::post('/orders/{order}/accept', [OrderActionController::class, 'accept'])->name('orders.accept');
    Route::post('/orders/{order}/complete', [OrderActionController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{order}/cancel', [OrderActionController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/pay-cash', [OrderActionController::class, 'payCash'])->name('orders.pay-cash');
    Route::post('/orders/{order}/pay-online', [OrderActionController::class, 'payOnline'])->name('orders.pay-online');
    Route::post('/orders/{order}/qris-confirm', [OrderActionController::class, 'confirmQris'])->name('orders.qris-confirm');

    Route::get('/receipt/{order}', [ReceiptController::class, 'show'])->name('receipt.show');
    Route::get('/receipt/{order}/print', [ReceiptController::class, 'print'])->name('receipt.print');

    Route::get('/history', [CashierController::class, 'history'])->name('history');
    Route::get('/shift', [ShiftController::class, 'show'])->name('shift');
});

Route::middleware(['auth', 'active', 'can:inventory.view'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
});

Route::prefix('kitchen')->name('kitchen.')->group(function () {
    Route::get('/', fn () => abort(404))->name('dashboard');
    Route::get('/board/freshness', fn () => abort(404))->name('board.freshness');
    Route::post('/orders/{order}/status', fn () => abort(404))->name('orders.status');
    Route::post('/orders/{order}/cancel', fn () => abort(404))->name('orders.cancel');
});

Route::middleware(['auth', 'active', 'role:admin|manager'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

    Route::resource('categories', CategoryController::class);
    Route::resource('products', ProductController::class);
    Route::resource('areas', AreaController::class);

    Route::get('/rooms', [RoomController::class, 'indexAll'])->name('rooms.index');
    Route::get('/rooms/create', [RoomController::class, 'create'])->name('rooms.create');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

    Route::get('/dining-tables', [DiningTableController::class, 'indexAll'])->name('dining-tables.index');
    Route::get('/dining-tables/create', [DiningTableController::class, 'create'])->name('dining-tables.create');
    Route::post('/dining-tables', [DiningTableController::class, 'store'])->name('dining-tables.store');
    Route::get('/dining-tables/{table}/edit', [DiningTableController::class, 'edit'])->name('dining-tables.edit');
    Route::put('/dining-tables/{table}', [DiningTableController::class, 'update'])->name('dining-tables.update');
    Route::delete('/dining-tables/{table}', [DiningTableController::class, 'destroy'])->name('dining-tables.destroy');
    Route::get('/dining-tables/{table}/qr-code', [LocationQrController::class, 'table'])->name('tables.qr');

    Route::get('/rooms/{room}/qr-code', [LocationQrController::class, 'room'])->name('rooms.qr');

    Route::resource('payment-methods', PaymentMethodController::class)->except('show');
    Route::resource('discounts', DiscountController::class)->except('show');
    Route::post('/discounts/weekly', [DiscountController::class, 'weekly'])->name('discounts.weekly');
    Route::resource('users', UserController::class)->except('show');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    Route::post('/inventory/{ingredient}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
    Route::get('/inventory/stock-in', [InventoryController::class, 'stockInForm'])->name('inventory.stock-in.form');
    Route::post('/inventory/stock-in', [InventoryController::class, 'stockIn'])->name('inventory.stock-in.store');
    Route::get('/inventory/stock-out', [InventoryController::class, 'stockOutForm'])->name('inventory.stock-out.form');
    Route::post('/inventory/stock-out', [InventoryController::class, 'stockOut'])->name('inventory.stock-out.store');
    Route::get('/inventory/transfer', [InventoryController::class, 'transferForm'])->name('inventory.transfer.form');
    Route::post('/inventory/transfer', [InventoryController::class, 'transfer'])->name('inventory.transfer.store');

    Route::resource('ingredients', IngredientController::class)->except('show');
    Route::resource('warehouses', WarehouseController::class)->except('show');
    Route::resource('suppliers', SupplierController::class)->except('show');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::get('/refunds/{payment}/create', [RefundController::class, 'create'])->name('refunds.create');
    Route::post('/refunds/{payment}', [RefundController::class, 'store'])->name('refunds.store');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});

Route::middleware(['auth', 'active'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/excel', [ReportController::class, 'excel'])->name('reports.excel');
    Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
});
