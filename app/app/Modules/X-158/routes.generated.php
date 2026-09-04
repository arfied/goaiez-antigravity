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

Route::middleware(['web', 'auth', TenantRoleMiddleware::class])->prefix('app/x-158')->group(function () {
    Route::get('/x-158/prospectfacing-video-demo', \App\Modules\X158\Ui\ProspectfacingVideoDemo::class)->name('x-158.prospectfacing-video-demo');
    Route::get('/x-158/content-plans-video', \App\Modules\X158\Ui\ContentPlansVideo::class)->name('x-158.content-plans-video');
    Route::get('/x-158/render-queue', \App\Modules\X158\Ui\RenderQueue::class)->name('x-158.render-queue');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-158')->group(function () {
    Route::get('/x-158/prospectfacing-video-demo', \App\Modules\X158\Ui\ProspectfacingVideoDemo::class)->name('x-158.prospectfacing-video-demo');
    Route::get('/x-158/content-plans-video', \App\Modules\X158\Ui\ContentPlansVideo::class)->name('x-158.content-plans-video');
    Route::get('/x-158/render-queue', \App\Modules\X158\Ui\RenderQueue::class)->name('x-158.render-queue');
});

