<?php

use App\Http\Controllers\ClassController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\ClassSessionController;
use App\Http\Controllers\Admin\AdminAuthController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;


use App\Services\GoogleScriptMailer;

/*
|--------------------------------------------------------------------------
| HOME / HEALTHCHECK
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

/*
|--------------------------------------------------------------------------
| UTILIDADES TEMPORALES (⚠️ BORRAR DESPUÉS)
|--------------------------------------------------------------------------
*/

// 🔹 Reset config y cachés
Route::get('/reset-config', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('optimize:clear');
    return '✔️ Config cleared';
});

// 🔹 Ejecutar migraciones en producción (TEMPORAL)
Route::get('/run-migrate', function () {
    try {
        Artisan::call('migrate', ['--force' => true]);
        return nl2br(Artisan::output()) . '<br><br>✔️ Migraciones ejecutadas.';
    } catch (\Throwable $e) {
        return '❌ Error ejecutando migraciones: ' . $e->getMessage();
    }
});

// 🔹 Ejecutar seeder de admin (TEMPORAL)
Route::get('/run-admin-seeder', function () {
    try {
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\AdminUserSeeder',
            '--force' => true,
        ]);
        return nl2br(Artisan::output()) . '<br><br>✔️ Admin seedeado correctamente.';
    } catch (\Throwable $e) {
        return '❌ Error ejecutando seeder: ' . $e->getMessage();
    }
});

// 🔹 Test rápido del Google Script Mailer (opcional)
Route::get('/test-google-mail', function () {
    $ok = GoogleScriptMailer::send(
        'risanchez@alonsoalonsolaw.com',
        'Paola Test',
        'Test desde GoogleScriptMailer ✅',
        '<h1>Hola Paola</h1><p>Si ves este correo, el Google Script funciona 🎉</p>',
        'Hola Paola, si ves este correo, el Google Script funciona.'
    );

    return $ok ? 'Correo enviado ✅' : 'Fallo el envío ❌ (revisa logs)';
});


/*
|--------------------------------------------------------------------------
| AUTH ADMIN (API)
|--------------------------------------------------------------------------
*/

Route::post('/api/admin/login', [AdminAuthController::class, 'login'])
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::middleware(['auth:sanctum'])->get('/api/user', function (Request $request) {
    $user = $request->user();
    return [
        'id' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
        'is_admin' => (bool) ($user->is_admin ?? false),
    ];
});

Route::middleware(['auth:sanctum'])->post('/api/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json([
        'ok' => true,
        'message' => 'Logged out',
    ]);
});


/*
|--------------------------------------------------------------------------
| API PÚBLICA (FORMULARIO)
|--------------------------------------------------------------------------
*/

Route::post('/api/bookings', [BookingController::class, 'store'])
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::get('/api/classes', [ClassSessionController::class, 'indexPublic']);


/*
|--------------------------------------------------------------------------
| ADMIN API PROTEGIDA
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // RESERVAS
    Route::get('/api/admin/bookings', [BookingController::class, 'index']);

    Route::put('/api/admin/bookings/{id}', [BookingController::class, 'update'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::delete('/api/admin/bookings/{id}', [BookingController::class, 'destroy'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::put('/api/admin/bookings/{id}/status', [BookingController::class, 'updateStatus'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    // 🔹 CLASES: ahora usando ClassController
    Route::get('/api/admin/classes', [ClassController::class, 'index']);

    Route::post('/api/admin/classes', [ClassController::class, 'store'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::put('/api/admin/classes/{id}', [ClassController::class, 'update'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::delete('/api/admin/classes/{id}', [ClassController::class, 'destroy'])
        ->withoutMiddleware([ValidateCsrfToken::class]);
});

/*
|--------------------------------------------------------------------------
| AUTH BREEZE
|--------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
