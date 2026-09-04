<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X166\Ui\ByService;
use App\Modules\X166\Ui\BySource;
use App\Modules\X166\Ui\ByTech;
use App\Modules\X166\Ui\MarginByJob;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-166')->group(function () {
    Route::get('/x-166/margin-by-job', MarginByJob::class)->name('x-166.margin-by-job');
    Route::get('/x-166/by-tech', ByTech::class)->name('x-166.by-tech');
    Route::get('/x-166/by-service', ByService::class)->name('x-166.by-service');
    Route::get('/x-166/by-source', BySource::class)->name('x-166.by-source');
});
