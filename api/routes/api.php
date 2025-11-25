<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\SalesController;

Route::prefix('inventory')->group(function () {
    Route::get('/', [InventoryController::class, 'index']);
    Route::post('/', [InventoryController::class, 'store']);
});

Route::prefix('sales')->group(function () {
    Route::post('/', [SalesController::class, 'store']);
    Route::get('/{sale}', [SalesController::class, 'show']);
});