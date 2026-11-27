<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\AboutController;
use App\Http\Controllers\Web\BenchmarkController;
use App\Http\Controllers\Web\CounterpartyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\OrderController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// Публічні сторінки для демонстрації на захисті.
Route::get('/benchmarks', [BenchmarkController::class, 'index'])->name('benchmarks');
Route::get('/about', AboutController::class)->name('about');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/counterparties', [CounterpartyController::class, 'index'])->name('counterparties.index');
    Route::get('/counterparties/{counterparty}', [CounterpartyController::class, 'show'])->name('counterparties.show');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/documents', [OrderController::class, 'generateDocument'])->name('orders.documents.store');

    Route::get('/templates', [DocumentController::class, 'templates'])->name('templates.index');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/export.json', [DocumentController::class, 'exportJson'])->name('documents.export.json');
    Route::get('/documents/{document}/export.csv', [DocumentController::class, 'exportCsv'])->name('documents.export.csv');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
