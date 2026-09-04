<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Modules\X158\Ui\ContentPlansVideo;
use App\Modules\X158\Ui\ProspectfacingVideoDemo;
use App\Modules\X158\Ui\RenderQueue;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Route;

app('router')->aliasMiddleware('tenant.role', function ($request, $next) {
    abort_unless(auth()->user()?->hasRole(UserRole::Owner, UserRole::Manager), 403);

    return $next($request);
});

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-158')->group(function () {
    Route::get('/x-158/prospectfacing-video-demo', ProspectfacingVideoDemo::class)->name('x-158.prospectfacing-video-demo');
    Route::get('/x-158/content-plans-video', ContentPlansVideo::class)->name('x-158.content-plans-video');
    Route::get('/x-158/render-queue', RenderQueue::class)->name('x-158.render-queue');
});

Route::middleware(['web', 'auth', 'can:'.AdminAccess::GATE])->prefix('admin/x-158')->group(function () {
    Route::get('/x-158/prospectfacing-video-demo', ProspectfacingVideoDemo::class)->name('x-158.prospectfacing-video-demo');
    Route::get('/x-158/content-plans-video', ContentPlansVideo::class)->name('x-158.content-plans-video');
    Route::get('/x-158/render-queue', RenderQueue::class)->name('x-158.render-queue');
});
