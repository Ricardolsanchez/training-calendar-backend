<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;

// Puedes dejarlo vacío o comentar todo de momento,
// porque este archivo no se está cargando.

use Illuminate\Support\Facades\DB;

Route::get('/health/db', function () {
    DB::select('select 1');
    return ['ok' => true];
});