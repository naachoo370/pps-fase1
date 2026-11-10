<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ItemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API común (prefijo /api automático)
|--------------------------------------------------------------------------
| Todas las respuestas son JSON. Las rutas de elementos exigen el token
| de sesión en la cabecera Authorization: Bearer <token>.
*/

Route::middleware('throttle:60,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('api.token')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/items', [ItemController::class, 'index']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::put('/items/{id}', [ItemController::class, 'update'])->whereNumber('id');
    Route::delete('/items/{id}', [ItemController::class, 'destroy'])->whereNumber('id');
});

// Cualquier otra ruta /api/* devuelve JSON, nunca una página HTML de error.
Route::fallback(fn () => response()->json(['error' => 'no encontrado'], 404));
