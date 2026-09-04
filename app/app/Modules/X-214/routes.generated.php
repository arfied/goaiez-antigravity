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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-214')->group(function () {
    Route::get('/surcharge-line', \App\Modules\X214\Ui\SurchargeLine::class)->name('x-214.surcharge-line');
    Route::get('/surcharge-disclosure', \App\Modules\X214\Ui\SurchargeDisclosure::class)->name('x-214.surcharge-disclosure');
});

