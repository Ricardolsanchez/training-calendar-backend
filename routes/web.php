<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\ClassSessionController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use App\Http\Controllers\Admin\AdminAuthController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

// ------------------------------
// HOME
// ------------------------------
Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// ----------------------------------------------------
// 🔹 RUTA TEMPORAL PARA PRUEBA DE CORREO CON BREVO 🔹
// (BORRAR O PROTEGER DESPUÉS)
// ----------------------------------------------------
Route::get('/test-mail', function () {
    Mail::raw('¡Hola Paola! Esto es una prueba desde Brevo API 📨', function ($m) {
        $m->to('risanchez@alonsoalonsolaw.com')   // 👈 pon aquí tu correo real
          ->subject('Prueba Brevo vía API ✔️');
    });

    return 'Correo de prueba enviado (si no ves error).';
});

// ----------------------------------------------------
// 🔹 DEBUG: VER SI BREVO_API_KEY ESTÁ CARGADA 🔹
// (ÚSALA SOLO PARA PROBAR, LUEGO BORRA ESTA RUTA)
// ----------------------------------------------------
Route::get('/debug-brevo-key', function () {
    $key = env('BREVO_API_KEY');

    return [
        'set'    => $key ? true : false,
        'length' => $key ? strlen($key) : 0,
    ];
});

// ----------------------------------------------------
// 🔹 RUTA TEMPORAL PARA CREAR / ACTUALIZAR EL ADMIN 🔹
// ----------------------------------------------------
Route::get('/run-admin-seeder', function () {
    try {
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\AdminUserSeeder',
            '--force' => true,
        ]);

        return nl2br(Artisan::output()) . '<br><br>✅ Admin seedeado correctamente.';
    } catch (\Throwable $e) {
        return '❌ Error ejecutando seeder: ' . $e->getMessage();
    }
});

Route::post('/api/admin/login', [AdminAuthController::class, 'login'])
    ->withoutMiddleware([ValidateCsrfToken::class]);

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
// ------------------------------
Route::post('/api/bookings', [BookingController::class, 'store'])
    ->withoutMiddleware([ValidateCsrfToken::class]);

// ------------------------------
// CLASES DISPONIBLES - PÚBLICO
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
