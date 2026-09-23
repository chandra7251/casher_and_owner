<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashierWorkspaceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderUpdateController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\OwnerMenuController;
use App\Http\Controllers\OwnerReportController;
use App\Http\Controllers\OwnerSettingsController;
use App\Http\Controllers\OwnerTableController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrintJobController;
use App\Http\Controllers\TableStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware(['guest', 'throttle:auth']);
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', CashierWorkspaceController::class)->name('cashier.workspace');
    Route::get('/cashier/orders', CashierWorkspaceController::class)->name('cashier.orders');
    Route::post('/cashier/orders', [OrderController::class, 'store'])->middleware('throttle:mutations')->name('cashier.orders.store');
    Route::patch('/cashier/orders/{order}', [OrderUpdateController::class, 'update'])->middleware('throttle:mutations');
    Route::post('/cashier/orders/{order}/payment', [PaymentController::class, 'store'])->middleware('throttle:mutations')->name('cashier.orders.payment');
    Route::post('/cashier/menu/items/{menuItem}/photo', [OwnerMenuController::class, 'updatePhoto'])->middleware('throttle:mutations');
    Route::patch('/cashier/tables/{table}/status', [TableStatusController::class, 'update'])->middleware('throttle:mutations');
    Route::get('/cashier/orders/{order}/receipt', [PaymentController::class, 'receipt']);
    Route::get('/cashier/print-jobs/failed', [PrintJobController::class, 'failed'])->middleware('throttle:mutations');
    Route::post('/cashier/print-jobs/{printJob}/retry', [PrintJobController::class, 'retry'])->middleware('throttle:mutations');
});

Route::middleware(['auth', 'role:owner'])->get('/owner/menu', [OwnerMenuController::class, 'index']);
Route::middleware(['auth', 'role:owner'])->post('/owner/menu/categories', [OwnerMenuController::class, 'storeCategory'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->patch('/owner/menu/categories/{menuCategory}', [OwnerMenuController::class, 'updateCategory'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->delete('/owner/menu/categories/{menuCategory}', [OwnerMenuController::class, 'destroyCategory'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->post('/owner/menu/items', [OwnerMenuController::class, 'storeItem'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->patch('/owner/menu/items/{menuItem}', [OwnerMenuController::class, 'updateItem'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->delete('/owner/menu/items/{menuItem}', [OwnerMenuController::class, 'destroyItem'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->get('/owner/dashboard', OwnerDashboardController::class);
Route::middleware(['auth', 'role:owner'])->get('/owner/tables', [OwnerTableController::class, 'index']);
Route::middleware(['auth', 'role:owner'])->patch('/owner/tables/{table}', [OwnerTableController::class, 'update'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->get('/owner/settings', [OwnerSettingsController::class, 'show']);
Route::middleware(['auth', 'role:owner'])->post('/owner/settings', [OwnerSettingsController::class, 'update'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->patch('/owner/settings', [OwnerSettingsController::class, 'update'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->get('/owner/reports', [OwnerReportController::class, 'index']);
Route::middleware(['auth', 'role:owner'])->get('/owner/reports/csv', [OwnerReportController::class, 'csv']);
Route::middleware(['auth', 'role:owner'])->get('/owner/reports/pdf', [OwnerReportController::class, 'pdf']);
Route::middleware(['auth', 'role:owner'])->patch('/owner/menu/sizes/{menuItemSize}', [OwnerMenuController::class, 'updateSize'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->post('/owner/menu/items/{menuItem}/photo', [OwnerMenuController::class, 'updatePhoto'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->patch('/owner/menu/items/{menuItem}/availability', [OwnerMenuController::class, 'updateAvailability'])->middleware('throttle:mutations');

Route::get('/health', fn () => response()->json(['ok' => true]));
Route::get('/api/health', fn () => response()->json(['ok' => true]));

Route::middleware(['throttle:print-bridge', 'print-bridge-auth'])->group(function () {
    Route::get('/api/print-jobs/next', [PrintJobController::class, 'next']);
    Route::patch('/api/print-jobs/{printJob}', [PrintJobController::class, 'update']);
});
