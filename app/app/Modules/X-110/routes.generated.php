<?php

declare(strict_types=1);

use App\Modules\X110\Ui\AbandonedForms;
use App\Modules\X110\Ui\Cooling;
use App\Modules\X110\Ui\InstallVerify;
use App\Modules\X110\Ui\TagVersionPer;
use App\Modules\X110\Ui\Today;
use App\Modules\X110\Ui\VisitorsLive;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-110')->group(function () {
    Route::get('/visitors-live', VisitorsLive::class)->name('x-110.visitors-live');
    Route::get('/today', Today::class)->name('x-110.today');
    Route::get('/cooling', Cooling::class)->name('x-110.cooling');
    Route::get('/abandoned-forms', AbandonedForms::class)->name('x-110.abandoned-forms');
    Route::get('/install-verify', InstallVerify::class)->name('x-110.install-verify');
    Route::get('/tag-version-per', TagVersionPer::class)->name('x-110.tag-version-per');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-110')->group(function () {
    Route::get('/visitors-live', VisitorsLive::class)->name('x-110.visitors-live.admin');
    Route::get('/today', Today::class)->name('x-110.today.admin');
    Route::get('/cooling', Cooling::class)->name('x-110.cooling.admin');
    Route::get('/abandoned-forms', AbandonedForms::class)->name('x-110.abandoned-forms.admin');
    Route::get('/install-verify', InstallVerify::class)->name('x-110.install-verify.admin');
    Route::get('/tag-version-per', TagVersionPer::class)->name('x-110.tag-version-per.admin');
});
