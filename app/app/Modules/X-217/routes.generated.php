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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-217')->group(function () {
    Route::get('/x-217/recruitpipeline', \App\Modules\X217\Ui\RecruitPipeline::class)->name('x-217.recruit-pipeline');
    Route::get('/x-217/offercomposer', \App\Modules\X217\Ui\OfferComposer::class)->name('x-217.offer-composer');
});

