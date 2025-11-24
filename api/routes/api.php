<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InventoryController;

Route::prefix('inventory')->group(function () {
    Route::get('/', [InventoryController::class, 'index']);
    Route::post('/', [InventoryController::class, 'store']);
});