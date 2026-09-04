<?php

use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(\App\Enums\UserRole::Owner, \App\Enums\UserRole::Manager), 403);
    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-214')->group(function () {
    Route::get('/x-214/surcharge-line', \App\Modules\X214\Ui\SurchargeLine::class)->name('x-214.surcharge-line');
    Route::get('/x-214/surcharge-disclosure', \App\Modules\X214\Ui\SurchargeDisclosure::class)->name('x-214.surcharge-disclosure');
});

