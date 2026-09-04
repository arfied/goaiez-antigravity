<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-176')->group(function () {
    Route::get('/x-176/seo-tab-website', \App\Modules\X176\Ui\SeoTabWebsite::class)->name('x-176.seo-tab-website');
});

