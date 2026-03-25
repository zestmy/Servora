<?php

use App\Http\Controllers\Lms\AuthController;
use App\Http\Controllers\Lms\SopPdfController;
use App\Livewire\Lms\Dashboard as LmsDashboard;
use App\Livewire\Lms\SopView as LmsSopView;
use Illuminate\Support\Facades\Route;

// Subdomain-based LMS routes (when on {slug}.servora.com.my)
// These routes work when ResolveCompanyFromSubdomain has set the company in the container
Route::prefix('lms')->group(function () {
    // Guest routes (subdomain provides the company context)
    Route::get('/login', [AuthController::class, 'showLogin'])->name('lms.subdomain.login');
    Route::post('/login', [AuthController::class, 'login'])->name('lms.subdomain.login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('lms.subdomain.register');
    Route::post('/register', [AuthController::class, 'register'])->name('lms.subdomain.register.submit');
});

// Legacy slug-based LMS routes (backward compatibility)
Route::prefix('lms/{companySlug}')->group(function () {
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
