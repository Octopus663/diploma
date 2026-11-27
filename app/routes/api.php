<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\CounterpartyController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Bench\CacheController;
use App\Http\Controllers\Bench\ComputeController;
use App\Http\Controllers\Bench\DatabaseController;
use App\Http\Controllers\Bench\DebugController;
use App\Http\Controllers\Bench\LeakDemoController;
use App\Http\Controllers\Bench\PingController;
use Illuminate\Support\Facades\Route;

// Сценарій 1: базовий роут для оцінки чистого навантаження фреймворку (публічний).
Route::get('/ping', PingController::class)->name('bench.ping');

// Автентифікація API (Sanctum).
Route::post('/auth/token', [AuthTokenController::class, 'store'])->middleware('throttle:auth')->name('api.token');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthTokenController::class, 'me'])->name('api.me');
    Route::delete('/auth/token', [AuthTokenController::class, 'destroy'])->name('api.token.destroy');

    Route::apiResource('counterparties', CounterpartyController::class)->names('api.counterparties');
    Route::apiResource('orders', OrderController::class)->names('api.orders');
    Route::apiResource('documents', DocumentController::class)->names('api.documents');

    Route::get('/templates', [TemplateController::class, 'index'])->name('api.templates.index');
    Route::get('/templates/{template}', [TemplateController::class, 'show'])->name('api.templates.show');
    Route::get('/products', [TemplateController::class, 'products'])->name('api.products.index');

    // Сценарії навантаження 2–4 (потребують токен Sanctum).
    Route::prefix('bench')->name('bench.')->group(function () {
        Route::get('/db/orders/{order}', [DatabaseController::class, 'orderReport'])->whereNumber('order')->name('db.order');
        Route::get('/db/counterparties/{counterparty}/orders', [DatabaseController::class, 'counterpartyOrders'])->whereNumber('counterparty')->name('db.counterparty');
        Route::get('/cache/counterparties/{counterparty}', [CacheController::class, 'show'])->whereNumber('counterparty')->name('cache.show');
        Route::post('/cache/counterparties/{counterparty}/hit', [CacheController::class, 'hit'])->whereNumber('counterparty')->name('cache.hit');
        Route::match(['get', 'post'], '/compute/report', ComputeController::class)->name('compute');
    });
});

// Службові маршрути етапу 6 (лише за прапорцями в config/bench.php).
if (config('bench.debug')) {
    Route::get('/_debug/memory', [DebugController::class, 'memory'])->name('bench.debug.memory');
}

if (config('bench.leak_demo')) {
    Route::prefix('leak')->name('leak.')->controller(LeakDemoController::class)->group(function () {
        Route::get('/static', 'staticLeak')->name('static');
        Route::get('/fixed/static', 'staticFixed')->name('static.fixed');
        Route::get('/singleton', 'singletonLeak')->name('singleton');
        Route::get('/fixed/singleton', 'singletonFixed')->name('singleton.fixed');
        Route::get('/memo', 'memoLeak')->name('memo');
        Route::get('/fixed/memo', 'memoFixed')->name('memo.fixed');
    });
}
