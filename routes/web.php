<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\AuditTrailController;
use App\Http\Controllers\Admin\CallbackLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentMethodMappingController;
use App\Http\Controllers\Admin\PaymentOrderController;
use App\Http\Controllers\Admin\PaymentProviderController;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebhookDeliveryController;
use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', '/admin/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/admin/login', [AdminAuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/admin/logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin.active'])->group(function () {
    Route::redirect('/', '/admin/dashboard');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics');

    Route::get('/transactions', [PaymentOrderController::class, 'index'])->name('transactions');
    Route::get('/transactions/{paymentOrder}', [PaymentOrderController::class, 'show'])->name('transactions.show');

    Route::get('/applications/create', [ApplicationController::class, 'create'])->name('applications.create');
    Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications', [ApplicationController::class, 'index'])->name('applications');
    Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::put('/applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');
    Route::post('/applications/{application}/rotate-api-key', [ApplicationController::class, 'rotateApiKey'])->name('applications.rotate-api-key');
    Route::post('/applications/{application}/rotate-webhook-secret', [ApplicationController::class, 'rotateWebhookSecret'])->name('applications.rotate-webhook-secret');
    Route::delete('/applications/{application}', [ApplicationController::class, 'destroy'])->name('applications.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/providers', [PaymentProviderController::class, 'index'])->name('providers');
    Route::get('/providers/{provider}', [PaymentProviderController::class, 'show'])->name('providers.show');
    Route::put('/providers/{provider}', [PaymentProviderController::class, 'update'])->name('providers.update');

    Route::get('/payment-methods', [PaymentMethodMappingController::class, 'index'])->name('payment-methods');
    Route::get('/callbacks', [CallbackLogController::class, 'index'])->name('callbacks');
    Route::get('/webhooks', [WebhookDeliveryController::class, 'index'])->name('webhooks');
    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail');
    Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation');
    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds');
});
