<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X194\Ui\AnyViewIt;
use App\Modules\X194\Ui\SavedViewsList;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-194')->group(function () {
    Route::get('/x-194/any-view-it', AnyViewIt::class)->name('x-194.any-view-it');
    Route::get('/x-194/saved-views-list', SavedViewsList::class)->name('x-194.saved-views-list');
});
