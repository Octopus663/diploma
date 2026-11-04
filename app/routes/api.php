<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\CounterpartyController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TemplateController;
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
});
