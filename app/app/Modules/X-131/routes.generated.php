<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-131')->group(function () {
    Route::get('/x-131/interest-tags', \App\Modules\X131\Ui\InterestTagsView::class)->name('x-131.interest-tags');
});

