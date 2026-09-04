<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X217\Ui\OfferComposer;
use App\Modules\X217\Ui\RecruitPipeline;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-217')->group(function () {
    Route::get('/x-217/recruitpipeline', RecruitPipeline::class)->name('x-217.recruit-pipeline');
    Route::get('/x-217/offercomposer', OfferComposer::class)->name('x-217.offer-composer');
});
