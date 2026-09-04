<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-110')->group(function () {
    Route::get('/x-110/visitors-live', \App\Modules\X110\Ui\VisitorsLive::class)->name('x-110.visitors-live');
    Route::get('/x-110/today', \App\Modules\X110\Ui\Today::class)->name('x-110.today');
    Route::get('/x-110/cooling', \App\Modules\X110\Ui\Cooling::class)->name('x-110.cooling');
    Route::get('/x-110/abandoned-forms', \App\Modules\X110\Ui\AbandonedForms::class)->name('x-110.abandoned-forms');
    Route::get('/x-110/install-verify', \App\Modules\X110\Ui\InstallVerify::class)->name('x-110.install-verify');
    Route::get('/x-110/tag-version-per', \App\Modules\X110\Ui\TagVersionPer::class)->name('x-110.tag-version-per');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-110')->group(function () {
    Route::get('/x-110/visitors-live', \App\Modules\X110\Ui\VisitorsLive::class)->name('x-110.visitors-live');
    Route::get('/x-110/today', \App\Modules\X110\Ui\Today::class)->name('x-110.today');
    Route::get('/x-110/cooling', \App\Modules\X110\Ui\Cooling::class)->name('x-110.cooling');
    Route::get('/x-110/abandoned-forms', \App\Modules\X110\Ui\AbandonedForms::class)->name('x-110.abandoned-forms');
    Route::get('/x-110/install-verify', \App\Modules\X110\Ui\InstallVerify::class)->name('x-110.install-verify');
    Route::get('/x-110/tag-version-per', \App\Modules\X110\Ui\TagVersionPer::class)->name('x-110.tag-version-per');
});

