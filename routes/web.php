<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\ClassSessionController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

// ------------------------------
// HOME
// ------------------------------
Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// ------------------------------
// LOGIN: usuario autenticado
// ------------------------------
Route::middleware(['auth:sanctum'])->get('/api/user', function (Request $request) {
    $user = $request->user();

    return [
        'id' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
        'is_admin' => (bool) ($user->is_admin ?? false),
    ];
});

// ------------------------------
// FORMULARIO PÚBLICO DE RESERVAS
// (desde React, sin CSRF clásico)
// ------------------------------
Route::post('/api/bookings', [BookingController::class, 'store'])
    ->withoutMiddleware([ValidateCsrfToken::class]);

// ------------------------------
// CLASES DISPONIBLES - PÚBLICO
// (para el BookingCalendar)
// ------------------------------
Route::get('/api/classes', [ClassSessionController::class, 'indexPublic']);
Route::post('/classes', [ClassSessionController::class, 'store'])
    ->withoutMiddleware([ValidateCsrfToken::class]);
Route::put('/classes/{id}', [ClassSessionController::class, 'update'])
    ->withoutMiddleware([ValidateCsrfToken::class]);
Route::delete('/classes/{id}', [ClassSessionController::class, 'destroy'])
    ->withoutMiddleware([ValidateCsrfToken::class]);

// ------------------------------
// ADMIN (todas protegidas con Sanctum)
// ------------------------------
Route::middleware(['auth:sanctum'])->group(function () {

    // ----- RESERVAS -----
    Route::get('/api/admin/bookings', [BookingController::class, 'index']);
    Route::put('/api/admin/bookings/{id}', [BookingController::class, 'update'])
        ->withoutMiddleware([ValidateCsrfToken::class]);
    Route::delete('/api/admin/bookings/{id}', [BookingController::class, 'destroy'])
        ->withoutMiddleware([ValidateCsrfToken::class]);
    Route::put('/api/admin/bookings/{id}/status', [BookingController::class, 'updateStatus'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    // ----- CLASES DISPONIBLES (CRUD) -----
    Route::get('/api/admin/classes', [ClassSessionController::class, 'index']);

    Route::post('/api/admin/classes', [ClassSessionController::class, 'store'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::put('/api/admin/classes/{id}', [ClassSessionController::class, 'update'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::delete('/api/admin/classes/{id}', [ClassSessionController::class, 'destroy'])
        ->withoutMiddleware([ValidateCsrfToken::class]);
});

// ------------------------------
// Auth de Breeze
// ------------------------------
require __DIR__ . '/auth.php';
