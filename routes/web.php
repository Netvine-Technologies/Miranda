<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SeoProductController;
use App\Http\Controllers\WebStoreController;
use App\Http\Controllers\WebProductController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    })->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::post('/logout', function (Request $request) {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    })->name('logout');

    Route::get('/stores', [WebStoreController::class, 'index'])->name('stores.index');
    Route::post('/stores', [WebStoreController::class, 'store'])->name('stores.store');
    Route::post('/stores/{store}/sync', [WebStoreController::class, 'sync'])->name('stores.sync');
    Route::post('/stores/{store}/set-interval', [WebStoreController::class, 'setInterval'])->name('stores.set-interval');
    Route::post('/admin/queue/work-once', [WebStoreController::class, 'queueWorkOnce'])->name('admin.queue.work-once');

    Route::get('/tracker/products', [WebProductController::class, 'index'])->name('tracker.products.index');
    Route::get('/tracker/products/{product}', [WebProductController::class, 'show'])->name('tracker.products.show');
    Route::get('/tracker/products/{product}/price-history', [WebProductController::class, 'history'])->name('tracker.products.history');
});

Route::get('/products/{handle}/price-history', [SeoProductController::class, 'priceHistoryByHandle'])
    ->name('products.seo.price-history');

Route::get('/compare/{slug}', [SeoProductController::class, 'compareBySlug'])
    ->name('products.seo.compare');
