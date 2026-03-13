<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentEventController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\ProviderCallbackController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\WebhookDeliveryController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('versioning.api.current', 'v1'))->group(function () {
    Route::get('/health', HealthController::class)->name('api.health');
    Route::post('/callback/{provider_code}', [ProviderCallbackController::class, 'store'])->name('api.callbacks.store');

    Route::middleware('api.key')->group(function () {
        Route::get('/providers', [ProviderController::class, 'index'])->name('api.providers.index');
        Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('api.payment-methods.index');

        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('api.payments.index');
            Route::post('/', [PaymentController::class, 'store'])->name('api.payments.store');
            Route::get('/lookup', [PaymentController::class, 'lookup'])->name('api.payments.lookup');
            Route::get('/{payment_id}', [PaymentController::class, 'show'])->name('api.payments.show');
            Route::get('/{payment_id}/events', [PaymentEventController::class, 'index'])->name('api.payments.events.index');
            Route::post('/{payment_id}/sync', [PaymentController::class, 'sync'])->name('api.payments.sync');
            Route::post('/{payment_id}/cancel', [PaymentController::class, 'cancel'])->name('api.payments.cancel');
            Route::post('/{payment_id}/refund', [PaymentController::class, 'refund'])->name('api.payments.refund');
        });

        Route::get('/webhook-deliveries', [WebhookDeliveryController::class, 'index'])->name('api.webhook-deliveries.index');
        Route::post('/webhook-deliveries/{delivery_id}/retry', [WebhookDeliveryController::class, 'retry'])->name('api.webhook-deliveries.retry');
    });
});
