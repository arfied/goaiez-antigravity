<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-140')->group(function () {
    Route::get('/x-140/proposed-pages', \App\Modules\X140\Ui\ProposedPagesView::class)->name('x-140.proposed-pages');
});

