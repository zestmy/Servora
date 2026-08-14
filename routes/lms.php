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

    /*
     * Learning & Development, trainee side.
     *
     * No `can:` middleware anywhere in here, and that is deliberate: the `lms`
     * guard is a different population from the `web` guard entirely. Trainees
     * hold no permissions — what they may see is decided by the outlets granted
     * to them in Settings > Training Portal, which every one of these screens
     * applies through visibleToOutlets(). A `can:` here would be checked
     * against a guard that has no abilities at all.
     */
    Route::get('/training', \App\Livewire\Lms\Courses::class)->name('lms.courses');
    Route::get('/training/{id}', \App\Livewire\Lms\CourseView::class)->name('lms.courses.show');
    Route::get('/quiz/{id}', \App\Livewire\Lms\QuizPlay::class)->name('lms.quiz.play');
    Route::get('/progress', \App\Livewire\Lms\MyProgress::class)->name('lms.progress');
});

/*
 * Joining a live session, OUTSIDE the auth group.
 *
 * Half the value of a live round is running one at a shift briefing where the
 * casuals have no portal account yet — they type a nickname and play. See the
 * migration note on training_session_players. A signed-in trainee reaching the
 * same URL is still picked up by the component and gets their score onto their
 * report card.
 */
Route::get('lms/live/{pin?}', \App\Livewire\Lms\LivePlay::class)->name('lms.live');
