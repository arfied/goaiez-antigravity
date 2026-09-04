<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X110\Ui\AbandonedForms;
use App\Modules\X110\Ui\Cooling;
use App\Modules\X110\Ui\InstallVerify;
use App\Modules\X110\Ui\TagVersionPer;
use App\Modules\X110\Ui\Today;
use App\Modules\X110\Ui\VisitorsLive;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-110')->group(function () {
    Route::get('/x-110/visitors-live', VisitorsLive::class)->name('x-110.visitors-live');
    Route::get('/x-110/today', Today::class)->name('x-110.today');
    Route::get('/x-110/cooling', Cooling::class)->name('x-110.cooling');
    Route::get('/x-110/abandoned-forms', AbandonedForms::class)->name('x-110.abandoned-forms');
    Route::get('/x-110/install-verify', InstallVerify::class)->name('x-110.install-verify');
    Route::get('/x-110/tag-version-per', TagVersionPer::class)->name('x-110.tag-version-per');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-110')->group(function () {
    Route::get('/x-110/visitors-live', VisitorsLive::class)->name('x-110.visitors-live');
    Route::get('/x-110/today', Today::class)->name('x-110.today');
    Route::get('/x-110/cooling', Cooling::class)->name('x-110.cooling');
    Route::get('/x-110/abandoned-forms', AbandonedForms::class)->name('x-110.abandoned-forms');
    Route::get('/x-110/install-verify', InstallVerify::class)->name('x-110.install-verify');
    Route::get('/x-110/tag-version-per', TagVersionPer::class)->name('x-110.tag-version-per');
});
