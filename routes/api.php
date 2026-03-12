<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyInternalApiSignature;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PriceWatchSubscriptionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/products/{product}/price-history', [ProductController::class, 'priceHistory']);
Route::post('/watch-subscriptions', [PriceWatchSubscriptionController::class, 'store'])
    ->middleware(['throttle:30,1', VerifyInternalApiSignature::class]);
Route::get('/watch-subscriptions/confirm/{token}', [PriceWatchSubscriptionController::class, 'confirm'])->name('watch-subscriptions.confirm');
Route::get('/watch-subscriptions/unsubscribe/{token}', [PriceWatchSubscriptionController::class, 'unsubscribe'])->name('watch-subscriptions.unsubscribe');
Route::get('/stores', [StoreController::class, 'index']);
Route::get('/stores/{store}', [StoreController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/stores', [StoreController::class, 'store']);
    Route::post('/stores/{store}/sync', [StoreController::class, 'sync']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
