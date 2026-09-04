<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

if (! class_exists('TenantRoleMiddleware')) {
    class TenantRoleMiddleware
    {
        public function handle($request, $next) {
            abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
            return $next($request);
        }
    }
}

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-110')->group(function () {
    Route::get('/visitors-live', \App\Modules\X110\Ui\VisitorsLive::class)->name('x-110.visitors-live');
    Route::get('/today', \App\Modules\X110\Ui\Today::class)->name('x-110.today');
    Route::get('/cooling', \App\Modules\X110\Ui\Cooling::class)->name('x-110.cooling');
    Route::get('/abandoned-forms', \App\Modules\X110\Ui\AbandonedForms::class)->name('x-110.abandoned-forms');
    Route::get('/install-verify', \App\Modules\X110\Ui\InstallVerify::class)->name('x-110.install-verify');
    Route::get('/tag-version-per', \App\Modules\X110\Ui\TagVersionPer::class)->name('x-110.tag-version-per');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-110')->group(function () {
    Route::get('/visitors-live', \App\Modules\X110\Ui\VisitorsLive::class)->name('x-110.visitors-live.admin');
    Route::get('/today', \App\Modules\X110\Ui\Today::class)->name('x-110.today.admin');
    Route::get('/cooling', \App\Modules\X110\Ui\Cooling::class)->name('x-110.cooling.admin');
    Route::get('/abandoned-forms', \App\Modules\X110\Ui\AbandonedForms::class)->name('x-110.abandoned-forms.admin');
    Route::get('/install-verify', \App\Modules\X110\Ui\InstallVerify::class)->name('x-110.install-verify.admin');
    Route::get('/tag-version-per', \App\Modules\X110\Ui\TagVersionPer::class)->name('x-110.tag-version-per.admin');
});

