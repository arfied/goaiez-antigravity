<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X191\Ui\LinksEarned;
use App\Modules\X191\Ui\PitchacquireRatio;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-191')->group(function () {
    Route::get('/x-191/links-earned', LinksEarned::class)->name('x-191.links-earned');
    Route::get('/x-191/pitchacquire-ratio', PitchacquireRatio::class)->name('x-191.pitchacquire-ratio');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-191')->group(function () {
    Route::get('/x-191/links-earned', LinksEarned::class)->name('x-191.links-earned');
    Route::get('/x-191/pitchacquire-ratio', PitchacquireRatio::class)->name('x-191.pitchacquire-ratio');
});
