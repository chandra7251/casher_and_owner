<?php

use App\Http\Controllers\CashierWorkspaceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PrintJobController;
use App\Http\Controllers\OwnerMenuController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\OwnerTableController;
use App\Http\Controllers\OwnerSettingsController;
use App\Http\Controllers\OwnerReportController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware(['guest', 'throttle:auth']);
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', CashierWorkspaceController::class)->name('cashier.workspace');
    Route::get('/cashier/orders', CashierWorkspaceController::class)->name('cashier.orders');
    Route::post('/cashier/orders', [OrderController::class, 'store'])->middleware('throttle:mutations')->name('cashier.orders.store');
    Route::post('/cashier/orders/{order}/payment', [PaymentController::class, 'store'])->middleware('throttle:mutations')->name('cashier.orders.payment');
    Route::get('/api/print-jobs/next', [PrintJobController::class, 'next'])->middleware('throttle:print-bridge');
    Route::patch('/api/print-jobs/{printJob}', [PrintJobController::class, 'update'])->middleware('throttle:print-bridge');
    Route::post('/api/print-jobs/{printJob}/retry', [PrintJobController::class, 'retry'])->middleware('throttle:print-bridge');
});

Route::middleware(['auth', 'role:owner'])->get('/owner/menu', [OwnerMenuController::class, 'index']);
Route::middleware(['auth', 'role:owner'])->get('/owner/dashboard', OwnerDashboardController::class);
Route::middleware(['auth', 'role:owner'])->get('/owner/tables', [OwnerTableController::class, 'index']);
Route::middleware(['auth', 'role:owner'])->patch('/owner/tables/{table}', [OwnerTableController::class, 'update'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->get('/owner/settings', [OwnerSettingsController::class, 'show']);
Route::middleware(['auth', 'role:owner'])->patch('/owner/settings', [OwnerSettingsController::class, 'update'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->get('/owner/reports', [OwnerReportController::class, 'index']);
Route::middleware(['auth', 'role:owner'])->get('/owner/reports/csv', [OwnerReportController::class, 'csv']);
Route::middleware(['auth', 'role:owner'])->patch('/owner/menu/sizes/{menuItemSize}', [OwnerMenuController::class, 'updateSize'])->middleware('throttle:mutations');
Route::middleware(['auth', 'role:owner'])->patch('/owner/menu/items/{menuItem}/availability', [OwnerMenuController::class, 'updateAvailability'])->middleware('throttle:mutations');

Route::get('/health', fn () => response()->json(['ok' => true]));
