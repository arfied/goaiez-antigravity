<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X175\Ui\ByDesign;
use App\Modules\X175\Ui\CustomerfacingNone;
use App\Modules\X175\Ui\StafffacingAssistantPanel;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-175')->group(function () {
    Route::get('/x-175/stafffacing-assistant-panel', StafffacingAssistantPanel::class)->name('x-175.stafffacing-assistant-panel');
    Route::get('/x-175/customerfacing-none', CustomerfacingNone::class)->name('x-175.customerfacing-none');
    Route::get('/x-175/by-design', ByDesign::class)->name('x-175.by-design');
});
