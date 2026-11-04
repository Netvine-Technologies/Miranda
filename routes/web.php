<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadDiscoveryController;
use App\Http\Controllers\CoursePartnerProspectController;
use App\Http\Controllers\CoursePartnerOpenController;
use App\Http\Controllers\CoursePartnerUnsubscribeController;
use App\Http\Controllers\CoursePartnerEmailActivityController;
use App\Http\Controllers\SearchDiscoveryController;
use App\Http\Controllers\SeoProductController;
use App\Http\Controllers\WebStoreController;
use App\Http\Controllers\WebProductController;
use App\Http\Controllers\ZoomPhoneController;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/course-partner-email/open/{token}.gif', CoursePartnerOpenController::class)
    ->where('token', '[0-9a-f]{64}')
    ->name('course-partners.email.open');

Route::get('/course-partner-email/unsubscribe/{token}', [CoursePartnerUnsubscribeController::class, 'show'])
    ->where('token', '[0-9a-f]{64}')
    ->name('course-partners.email.unsubscribe.show');
Route::post('/course-partner-email/unsubscribe/{token}', [CoursePartnerUnsubscribeController::class, 'store'])
    ->where('token', '[0-9a-f]{64}')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('course-partners.email.unsubscribe.store');

Route::match(['get', 'post'], '/setup-owner', function (Request $request) {
    abort_if(User::query()->exists(), 404);

    if ($request->isMethod('get')) {
        return view('auth.setup-owner', [
            'name' => 'Shane Johnson',
            'email' => 'info@netvinetechnologies.co.uk',
        ]);
    }

    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'confirmed', Password::min(12)],
    ]);

    $user = User::query()->create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
    ]);

    Auth::login($user);
    $request->session()->regenerate();

    return redirect()->route('dashboard');
})->middleware(['guest', 'signed'])->name('setup.owner');

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

    Route::get('/leads/discovery', [LeadDiscoveryController::class, 'index'])->name('leads.discovery.index');
    Route::post('/leads/discovery', [LeadDiscoveryController::class, 'start'])->name('leads.discovery.start');
    Route::get('/leads/discovery/status', [LeadDiscoveryController::class, 'status'])->name('leads.discovery.status');
    Route::get('/leads/discovery/course-campaign/{leadScanRun}/export', [LeadDiscoveryController::class, 'exportCourseCampaign'])->name('leads.discovery.course-campaign.export');
    Route::get('/leads/course-partners', [CoursePartnerProspectController::class, 'index'])->name('leads.course-partners.index');
    Route::get('/leads/course-partners/emails', CoursePartnerEmailActivityController::class)->name('leads.course-partners.emails');
    Route::post('/leads/course-partners/emails/queue-control', [CoursePartnerEmailActivityController::class, 'control'])->name('leads.course-partners.queue-control');
    Route::get('/leads/course-partners/export', [CoursePartnerProspectController::class, 'export'])->name('leads.course-partners.export');
    Route::patch('/leads/course-partners/{coursePartnerProspect}', [CoursePartnerProspectController::class, 'update'])->name('leads.course-partners.update');
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('/leads/{businessLead}', [LeadController::class, 'show'])->name('leads.show');
    Route::post('/leads/{businessLead}/notes', [LeadController::class, 'storeNote'])->name('leads.notes.store');
    Route::get('/zoom-phone', [ZoomPhoneController::class, 'index'])->name('zoom-phone.index');
    Route::post('/zoom-phone/events', [ZoomPhoneController::class, 'storeEvent'])->name('zoom-phone.events');
    Route::post('/zoom-phone/sync', [ZoomPhoneController::class, 'sync'])->name('zoom-phone.sync');
    Route::get('/search-discovery', [SearchDiscoveryController::class, 'index'])->name('search-discovery.index');
    Route::post('/search-discovery', [SearchDiscoveryController::class, 'run'])->name('search-discovery.run');
    Route::get('/search-discovery/export', [SearchDiscoveryController::class, 'export'])->name('search-discovery.export');
    Route::get('/search-discovery/{searchDiscoveryLead}', [SearchDiscoveryController::class, 'show'])->name('search-discovery.show');
    Route::patch('/search-discovery/{searchDiscoveryLead}', [SearchDiscoveryController::class, 'update'])->name('search-discovery.update');
});

Route::get('/products/{handle}/price-history', [SeoProductController::class, 'priceHistoryByHandle'])
    ->name('products.seo.price-history');

Route::get('/compare/{slug}', [SeoProductController::class, 'compareBySlug'])
    ->name('products.seo.compare');
