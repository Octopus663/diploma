<?php

use App\Http\Controllers\Bench\PingController;
use Illuminate\Support\Facades\Route;

// Сценарій 1: базовий роут для оцінки чистого навантаження фреймворку.
Route::get('/ping', PingController::class)->name('bench.ping');
