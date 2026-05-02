<?php

use Illuminate\Support\Facades\Route;
use Marvel\Http\Controllers\PlaceController;

Route::middleware(['auth:api'])->group(function () {
    Route::get('/places', [PlaceController::class, 'index']);
    Route::get('/places/{id}', [PlaceController::class, 'show']);
    Route::post('/places', [PlaceController::class, 'store']);
    Route::put('/places/{id}', [PlaceController::class, 'update']);
    Route::delete('/places/{id}', [PlaceController::class, 'destroy']);
    Route::get('/places/search/products', [PlaceController::class, 'searchProducts']);
}); 