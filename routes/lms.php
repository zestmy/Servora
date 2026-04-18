<?php

use App\Http\Controllers\Lms\AuthController;
use App\Http\Controllers\Lms\SopPdfController;
use App\Livewire\Lms\Dashboard as LmsDashboard;
use App\Livewire\Lms\SopView as LmsSopView;
use Illuminate\Support\Facades\Route;

// Subdomain-based LMS routes ({slug}.servora.com.my/lms)
// ResolveCompanyFromSubdomain middleware resolves the company from subdomain
// lms.guest sends already-authenticated users straight to the dashboard so a
// stale/cached login page can never intercept them with a 419.
Route::prefix('lms')->middleware(['company.subdomain', 'lms.guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('lms.subdomain.login');
    Route::post('/login', [AuthController::class, 'login'])->name('lms.subdomain.login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('lms.subdomain.register');
    Route::post('/register', [AuthController::class, 'register'])->name('lms.subdomain.register.submit');
});

// Path-based LMS routes (servora.com.my/lms/{slug})
Route::prefix('lms/{companySlug}')->middleware('lms.guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('lms.login');
    Route::post('/login', [AuthController::class, 'login'])->name('lms.login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('lms.register');
    Route::post('/register', [AuthController::class, 'register'])->name('lms.register.submit');
});

// Authenticated LMS routes
Route::prefix('lms')->middleware('lms.auth')->group(function () {
    Route::get('/', LmsDashboard::class)->name('lms.dashboard');
    Route::get('/sop/{id}', LmsSopView::class)->name('lms.sop.show');
    Route::get('/sop/{id}/pdf', [SopPdfController::class, 'single'])->name('lms.sop.pdf');
    Route::post('/logout', [AuthController::class, 'logout'])->name('lms.logout');
});
