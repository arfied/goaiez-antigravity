<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X214\Ui\SurchargeDisclosure;
use App\Modules\X214\Ui\SurchargeLine;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-214')->group(function () {
    Route::get('/x-214/surcharge-line', SurchargeLine::class)->name('x-214.surcharge-line');
    Route::get('/x-214/surcharge-disclosure', SurchargeDisclosure::class)->name('x-214.surcharge-disclosure');
});
