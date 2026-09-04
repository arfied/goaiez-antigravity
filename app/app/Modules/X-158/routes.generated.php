<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.role'])->prefix('app/x-158')->group(function () {
    Route::get('/prospectfacing-video-demo', \App\Modules\X158\Ui\ProspectfacingVideoDemo::class)->name('x-158.prospectfacing-video-demo');
    Route::get('/content-plans-video', \App\Modules\X158\Ui\ContentPlansVideo::class)->name('x-158.content-plans-video');
    Route::get('/render-queue', \App\Modules\X158\Ui\RenderQueue::class)->name('x-158.render-queue');
});

Route::middleware(['web', 'auth', 'can:' . \App\Support\Admin\AdminAccess::GATE])->prefix('admin/x-158')->group(function () {
    Route::get('/prospectfacing-video-demo', \App\Modules\X158\Ui\ProspectfacingVideoDemo::class)->name('x-158.prospectfacing-video-demo.admin');
    Route::get('/content-plans-video', \App\Modules\X158\Ui\ContentPlansVideo::class)->name('x-158.content-plans-video.admin');
    Route::get('/render-queue', \App\Modules\X158\Ui\RenderQueue::class)->name('x-158.render-queue.admin');
});

