<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\ClassSessionController;
use App\Http\Controllers\Admin\AdminAuthController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

use App\Services\GoogleScriptMailer;

// ========================== HOME / HEALTHCHECK ==========================

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// ========================== HELPERS TEMPORALES ==========================

// 🔹 Test rápido del GoogleScriptMailer
Route::get('/test-google-mail', function () {
    $ok = GoogleScriptMailer::send(
        'risanchez@alonsoalonsolaw.com',   // cámbialo si quieres probar otro correo
        'Paola Test',
        'Test desde GoogleScriptMailer ✅',
        '<h1>Hola Paola</h1><p>Si ves este correo, el Google Script funciona 🎉</p>',
        'Hola Paola, si ves este correo, el Google Script funciona.'
    );

    return $ok ? 'Correo enviado ✅' : 'Fallo el envío ❌ (revisa logs)';
});

// 🔹 Reset de cachés de config (bórrala cuando ya no la uses)
Route::get('/reset-config', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('optimize:clear');
    return 'Config cleared ✔️';
});

// 🔹 Ejecutar seeder de admin (también temporal)
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

// ========================== AUTH ADMIN (API) ==========================

// Login admin (usa Sanctum, sin CSRF porque viene del front)
Route::post('/api/admin/login', [AdminAuthController::class, 'login'])
    ->withoutMiddleware([ValidateCsrfToken::class]);

// Datos del usuario autenticado (admin o no)
Route::middleware(['auth:sanctum'])->get('/api/user', function (Request $request) {
    $user = $request->user();

    return [
        'id'       => $user->id,
        'email'    => $user->email,
        'name'     => $user->name,
        'is_admin' => (bool) ($user->is_admin ?? false),
    ];
});

// Logout admin (cierra sesión web + invalida sesion)
Route::middleware(['auth:sanctum'])->post('/api/logout', function (Request $request) {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json([
        'ok'      => true,
        'message' => 'Logged out',
    ]);
});

// ========================== FORMULARIO PÚBLICO ==========================

// Crear reserva pública (sin CSRF)
Route::post('/api/bookings', [BookingController::class, 'store'])
    ->withoutMiddleware([ValidateCsrfToken::class]);

// Listar clases disponibles (público)
Route::get('/api/classes', [ClassSessionController::class, 'indexPublic']);

// ========================== ADMIN API PROTEGIDA ==========================

Route::middleware(['auth:sanctum'])->group(function () {

    // ---------- RESERVAS (ADMIN) ----------

    Route::get('/api/admin/bookings', [BookingController::class, 'index']);

    Route::put('/api/admin/bookings/{id}', [BookingController::class, 'update'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::delete('/api/admin/bookings/{id}', [BookingController::class, 'destroy'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::put('/api/admin/bookings/{id}/status', [BookingController::class, 'updateStatus'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    // ---------- CLASES (ADMIN CRUD) ----------

    Route::get('/api/admin/classes', [ClassSessionController::class, 'index']);

    Route::post('/api/admin/classes', [ClassSessionController::class, 'store'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::put('/api/admin/classes/{id}', [ClassSessionController::class, 'update'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::delete('/api/admin/classes/{id}', [ClassSessionController::class, 'destroy'])
        ->withoutMiddleware([ValidateCsrfToken::class]);
});

// ========================== AUTH BREEZE POR DEFECTO =====================

require __DIR__ . '/auth.php';
