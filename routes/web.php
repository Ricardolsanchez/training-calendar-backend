<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\Admin\ClassSessionController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminStatsController;

use App\Services\GoogleScriptMailer;

/*
|--------------------------------------------------------------------------
| HOME / HEALTHCHECK
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/api/health/db', function () {
    DB::select('select 1');
    return response()->json(['ok' => true]);
});

/*
|---------------------------------------------------------------------------
| UTILIDADES TEMPORALES (⚠️ BORRAR DESPUÉS)
|---------------------------------------------------------------------------
*/

Route::get('/reset-config', function () {
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('optimize:clear');
    return '✔️ Config cleared';
});

Route::get('/run-migrate', function () {
    try {
        Artisan::call('migrate', ['--force' => true]);
        return nl2br(Artisan::output()) . '<br><br>✔️ Migraciones ejecutadas.';
    } catch (\Throwable $e) {
        return '❌ Error ejecutando migraciones: ' . $e->getMessage();
    }
});

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
|---------------------------------------------------------------------------
| AUTH ADMIN (API)
|---------------------------------------------------------------------------
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
|---------------------------------------------------------------------------
| API PÚBLICA (FORMULARIO + CALENDARIO)
|---------------------------------------------------------------------------
*/

Route::post('/api/bookings', [BookingController::class, 'store'])
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::get('/api/classes', [ClassSessionController::class, 'indexPublic']);

/*
|---------------------------------------------------------------------------
| ADMIN API PROTEGIDA
|---------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // ===== RESERVAS =====
    Route::get('/api/admin/bookings', [BookingController::class, 'index']);

    Route::put('/api/admin/bookings/{id}/attendance', [BookingController::class, 'updateAttendance'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::put('/api/admin/bookings/{id}/status', [BookingController::class, 'updateStatus'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::delete('/api/admin/bookings/{id}', [BookingController::class, 'destroy'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    // ===== STATS / KPIS =====
    Route::get('/api/admin/stats/kpis', [AdminStatsController::class, 'index']);

    // ===== CLASES ADMIN =====
    Route::get('/api/admin/classes', [ClassSessionController::class, 'index']);

    Route::post('/api/admin/classes', [ClassSessionController::class, 'store'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::put('/api/admin/classes/{id}', [ClassSessionController::class, 'update'])
        ->withoutMiddleware([ValidateCsrfToken::class]);

    Route::delete('/api/admin/classes/{id}', [ClassSessionController::class, 'destroy'])
        ->withoutMiddleware([ValidateCsrfToken::class]);
});

/*
|---------------------------------------------------------------------------
| AUTH BREEZE
|---------------------------------------------------------------------------
*/

require __DIR__ . '/auth.php';
